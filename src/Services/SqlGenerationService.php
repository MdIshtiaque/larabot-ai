<?php

declare(strict_types=1);

namespace Emon\LarabotAi\Services;

use Illuminate\Support\Facades\Log;

class SqlGenerationService
{
    public function __construct(
        private GeminiService $geminiService,
        private SchemaRetrievalService $schemaRetrieval
    ) {}

    /**
     * Generate SQL query from natural language
     */
    public function generateSql(string $query, int $retryCount = 0): ?array
    {
        // Retrieve relevant tables
        $relevantTables = $this->schemaRetrieval->retrieveRelevantTables($query, 5);
        if (empty($relevantTables)) {
            Log::warning('No relevant tables found for query', ['query' => $query]);

            return null;
        }

        // Format schema for prompt
        $schemaContext = $this->schemaRetrieval->formatForPrompt($relevantTables);
        // Build prompt for SQL generation
        $prompt = $this->buildSqlPrompt($query, $schemaContext);

        // Generate SQL using Gemini
        $generatedSql = $this->geminiService->generateText($prompt, [
            'temperature' => 0.1, // Low temperature for more deterministic SQL
            'maxOutputTokens' => 1024,
        ]);

        if ($generatedSql === null) {
            return null;
        }

        // Extract SQL from response (remove markdown code blocks if present)
        $sql = $this->extractSql($generatedSql);

        return [
            'sql' => $sql,
            'tables_used' => $this->schemaRetrieval->getTableNames($relevantTables),
            'raw_response' => $generatedSql,
            'retry_count' => $retryCount,
        ];
    }

    /**
     * Build prompt for SQL generation
     */
    private function buildSqlPrompt(string $userQuery, string $schemaContext): string
    {
        return <<<PROMPT
You are a MySQL SQL query generator. Your task is to generate a valid SQL SELECT query based on the user's natural language question.

{$schemaContext}

Core Rules:
1. Generate ONLY ONE SELECT query (no INSERT, UPDATE, DELETE, DROP, ALTER, CREATE)
2. Use ONLY the tables and columns provided in the schema above
3. Return ONLY the SQL query without any explanation, markdown, or formatting
4. Use proper JOIN syntax when multiple tables are needed
5. Add appropriate WHERE clauses based on the question context
6. Use LIMIT 100 if no specific limit is mentioned in the question
7. Use meaningful table aliases (e.g., t1, t2 or first letter of table name)
8. Follow MySQL syntax strictly with ONLY_FULL_GROUP_BY mode enabled
9. NEVER include password, password_hash, or any sensitive authentication fields in SELECT statements
10. Exclude columns containing 'password', 'secret', 'token', 'key' in their names from results


Critical SQL Constraints:
9. NEVER mix aggregate functions (COUNT, SUM, AVG, MAX, MIN) with non-aggregated columns unless using GROUP BY
10. If question asks for both count AND details, prefer showing details (row count indicates quantity)
11. Carefully identify the PRIMARY entity being asked about based on question keywords
12. Use DISTINCT when joining tables to avoid duplicate rows

Table Selection Strategy (CRITICAL):
- The PRIMARY entity is the one being asked about (the subject of the sentence)
- Pattern: "Show/Get/Find/List [PRIMARY_ENTITY] ..." → SELECT FROM PRIMARY_ENTITY
- Pattern: "How many [PRIMARY_ENTITY] ..." → SELECT FROM PRIMARY_ENTITY
- Pattern: "[PRIMARY_ENTITY] who/that/which [verb] [SECONDARY_ENTITY]" → SELECT FROM PRIMARY_ENTITY (join SECONDARY_ENTITY)

Examples:
- "Show USERS who have orders" → SELECT FROM users (NOT orders)
- "Show ORDERS that belong to users" → SELECT FROM orders (NOT users)
- "List EMPLOYEES in departments" → SELECT FROM employees (NOT departments)
- "Get PRODUCTS with categories" → SELECT FROM products (NOT categories)
- "Find CUSTOMERS who made purchases" → SELECT FROM customers (NOT purchases)

SQL Syntax Examples:
❌ INVALID:
- SELECT COUNT(id), name FROM table_x (aggregate + non-aggregate without GROUP BY)
- SELECT * FROM table1; SELECT * FROM table2; (multiple statements)
- SELECT t1.*, t2.name FROM ... (mixing aggregate context incorrectly)

✅ VALID:
- SELECT id, name, email FROM table_x WHERE status = 'active'
- SELECT COUNT(*) as total FROM table_x WHERE created_at > '2024-01-01'
- SELECT DISTINCT t1.name FROM table1 t1 JOIN table2 t2 ON t1.id = t2.ref_id
- SELECT category, COUNT(*) as count FROM table_x GROUP BY category
- SELECT status, AVG(amount) as avg_amount FROM table_x GROUP BY status

Important JOIN Guidelines:
- Always use explicit JOIN conditions (ON clause)
- Use INNER JOIN for "has/contains" relationships
- Use LEFT JOIN only when specifically asked for "including those without"
- Reference foreign key relationships from the schema above

Entity Name Extraction:
- When user mentions entity names with type suffix, remove the type word
- Example: "Sunset Vegas Tenant" → use "Sunset Vegas" (remove "Tenant")
- Example: "John Doe User" → use "John Doe" (remove "User")
- Example: "Building A Property" → use "Building A" (remove "Property")
- Use LIKE operator for partial name matching when appropriate

User Question: "{$userQuery}"

Analyze the question, identify the primary entity, extract clean entity names, and generate ONE valid MySQL SELECT query:
PROMPT;
    }

    /**
     * Extract SQL from generated response (remove markdown blocks)
     */
    private function extractSql(string $response): string
    {
        // Remove markdown code blocks
        $sql = preg_replace('/```sql\s*\n/i', '', $response);
        $sql = preg_replace('/```\s*$/i', '', $sql);
        $sql = trim($sql);

        // Ensure it ends with semicolon
        if (! str_ends_with($sql, ';')) {
            $sql .= ';';
        }

        return $sql;
    }

    /**
     * Validate generated SQL for safety
     */
    public function validateSql(string $sql, array $allowedTables = []): array
    {
        $errors = [];

        // Check for dangerous operations
        $dangerousPatterns = [
            '/\b(DROP|DELETE|UPDATE|INSERT|TRUNCATE|ALTER|CREATE)\b/i' => 'Dangerous operation detected',
            '/;\s*\w+/i' => 'Multiple statements not allowed',
            '/--|\#|\/\*/i' => 'SQL comments not allowed',
            '/\bINTO\s+OUTFILE\b/i' => 'File operations not allowed',
            '/\bLOAD_FILE\b/i' => 'File operations not allowed',
        ];

        foreach ($dangerousPatterns as $pattern => $error) {
            if (preg_match($pattern, $sql)) {
                $errors[] = $error;
            }
        }

        // Ensure SELECT query
        if (! preg_match('/^\s*SELECT\b/i', $sql)) {
            $errors[] = 'Query must start with SELECT';
        }

        // Check for invalid aggregate + non-aggregate mix (ONLY_FULL_GROUP_BY)
        if (preg_match('/\b(COUNT|SUM|AVG|MAX|MIN)\s*\(/i', $sql)) {
            // Has aggregate function
            if (! preg_match('/\bGROUP\s+BY\b/i', $sql)) {
                // No GROUP BY - check if mixing with other columns
                if (preg_match('/SELECT\s+.*?(COUNT|SUM|AVG|MAX|MIN)\s*\([^)]+\)\s*,\s*\w+/i', $sql)) {
                    $errors[] = 'Cannot mix aggregate functions with non-aggregated columns without GROUP BY';
                }
            }
        }

        // Validate table names if provided
        if (! empty($allowedTables)) {
            // Extract table names from SQL
            preg_match_all('/FROM\s+`?(\w+)`?|JOIN\s+`?(\w+)`?/i', $sql, $matches);
            $usedTables = array_filter(array_merge($matches[1], $matches[2]));

            // Get all related tables via foreign keys
            $relatedTables = $this->getRelatedTables($allowedTables);
            $allAllowedTables = array_merge($allowedTables, $relatedTables);

            foreach ($usedTables as $table) {
                if (! in_array($table, $allAllowedTables)) {
                    $errors[] = "Table '{$table}' is not in the allowed or related tables list";
                }
            }
        }

        // Add LIMIT if not present
        if (! preg_match('/\bLIMIT\s+\d+/i', $sql)) {
            $sql = rtrim($sql, ';').' LIMIT 100;';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'sql' => $sql,
        ];
    }

    /**
     * Get related tables via foreign key relationships
     */
    private function getRelatedTables(array $tables): array
    {
        $related = [];

        foreach ($tables as $tableName) {
            $schema = \DB::table('schema_embeddings')
                ->where('table_name', $tableName)
                ->first();

            if ($schema) {
                $relationships = json_decode($schema->relationships, true) ?? [];
                foreach ($relationships as $rel) {
                    if (isset($rel['references_table'])) {
                        $related[] = $rel['references_table'];
                    }
                }
            }
        }

        return array_unique($related);
    }
}
