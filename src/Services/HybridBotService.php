<?php

declare(strict_types=1);

namespace Emon\LarabotAi\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HybridBotService
{
    public function __construct(
        private SqlGenerationService $sqlGenerator,
        private RagService $ragService,
        private GeminiService $geminiService
    ) {}

    /**
     * Process user query and determine intent
     */
    public function ask(string $query, ?string $userId = null): array
    {
        $startTime = microtime(true);
        // Detect intent
        $intent = $this->detectIntent($query);
        $result = match ($intent) {
            'sql' => $this->handleSqlQuery($query),
            'rag' => $this->handleRagQuery($query),
            'hybrid' => $this->handleHybridQuery($query),
            default => $this->handleRagQuery($query),
        };

        $responseTime = (int) ((microtime(true) - $startTime) * 1000);

        // Log query
        $this->logQuery($userId, $query, $intent, $result, $responseTime);

        return array_merge($result, [
            'intent' => $intent,
            'response_time_ms' => $responseTime,
        ]);
    }

    /**
     * Detect query intent using AI (sql, rag, or hybrid)
     */
    private function detectIntent(string $query): string
    {
        try {
            // Get available database tables context
            $availableTables = $this->getAvailableTablesContext();

            // Get available knowledge base topics
            $knowledgeTopics = $this->getKnowledgeBaseContext();

            $prompt = <<<PROMPT
You are an intelligent query router for a hybrid AI system. Analyze the user's query and determine the best intent.

**Available Data Sources:**

1. **SQL Database (schema_embeddings):**
   - Contains structured data tables for querying, filtering, counting, and analytics
   - Available tables: {$availableTables}
   - Use when: Query needs to fetch, count, filter, aggregate, or analyze structured data

2. **Knowledge Base (knowledge_chunks):**
   - Contains documentation, guides, policies, procedures, and conceptual information
   - Available topics: {$knowledgeTopics}
   - Use when: Query asks for explanations, definitions, how-to guides, or conceptual understanding

3. **Hybrid:**
   - Combines both database queries AND documentation
   - Use when: Query needs both data analysis AND contextual explanation
   - Example: "Show me top 10 users and explain our user verification policy"

**User Query:** "{$query}"

**CRITICAL RULES:**
1. Return ONLY a single word: "sql" OR "rag" OR "hybrid"
2. NO explanations, NO reasoning, NO additional text
3. NO JSON, NO markdown, just the single intent word

**Decision Logic:**
- Choose "sql" if query needs to retrieve, count, filter, aggregate, or analyze database records
- Choose "rag" if query needs explanations, definitions, procedures, policies, or conceptual knowledge
- Choose "hybrid" if query explicitly or implicitly needs BOTH data AND explanation

RESPOND WITH ONLY ONE WORD:
PROMPT;

            $response = $this->geminiService->generateText($prompt, [
                'temperature' => 0.1,  // Very low temperature for consistent classification
                'maxOutputTokens' => 10,  // We only need one word
            ]);

            $intent = strtolower(trim($response));

            // Validate the response
            if (in_array($intent, ['sql', 'rag', 'hybrid'])) {
                return $intent;
            }

            // Fallback: If AI returns invalid response, use RAG as safe default
            Log::warning('Invalid intent detected from AI', [
                'query' => $query,
                'ai_response' => $response,
            ]);

            return 'rag';

        } catch (\Exception $e) {
            Log::error('Intent detection failed', [
                'query' => $query,
                'error' => $e->getMessage(),
            ]);

            // Fallback to RAG on error
            return 'rag';
        }
    }

    /**
     * Get available database tables context for intent detection
     */
    private function getAvailableTablesContext(): string
    {
        try {
            // Get sample of table names from schema_embeddings
            $tables = DB::table('schema_embeddings')
                ->select('table_name', 'summary')
                ->limit(20)
                ->get();

            if ($tables->isEmpty()) {
                return 'No database tables indexed yet';
            }

            $tableList = $tables->map(fn ($t) => $t->table_name)->implode(', ');

            return $tableList;
        } catch (\Exception $e) {
            return 'Database schema not available';
        }
    }

    /**
     * Get available knowledge base topics for intent detection
     */
    private function getKnowledgeBaseContext(): string
    {
        try {
            // Get sample of unique source files from knowledge_chunks
            $sources = DB::table('knowledge_chunks')
                ->select('source_file', 'source_type')
                ->distinct()
                ->limit(15)
                ->get();

            if ($sources->isEmpty()) {
                return 'No documentation indexed yet';
            }

            $topicsList = $sources->map(function ($s) {
                $fileName = basename($s->source_file, '.md');

                return str_replace(['-', '_'], ' ', $fileName);
            })->implode(', ');

            return $topicsList;
        } catch (\Exception $e) {
            return 'Knowledge base not available';
        }
    }

    /**
     * Handle SQL-based queries
     */
    private function handleSqlQuery(string $query): array
    {
        $generated = $this->sqlGenerator->generateSql($query);
        if ($generated === null) {
            return [
                'success' => false,
                'error' => 'Could not generate SQL for this query',
                'answer' => null,
                'html' => null,
            ];
        }

        // Validate SQL
        $validation = $this->sqlGenerator->validateSql(
            $generated['sql'],
            $generated['tables_used']
        );

        if (! $validation['valid']) {
            return [
                'success' => false,
                'error' => 'Generated SQL failed validation: '.implode(', ', $validation['errors']),
                'sql' => $generated['sql'],
                'answer' => null,
                'html' => null,
            ];
        }

        // Execute SQL on read-only connection
        try {
            $data = DB::connection('mysql_readonly')->select($validation['sql']);

            // Format result with NL + HTML visualization
            $formatted = $this->formatSqlResult($query, $data);

            return [
                'success' => true,
                'sql' => $validation['sql'],
                'answer' => $formatted['answer'],
                'html' => $formatted['html'],
                'visualization_type' => $formatted['visualization_type'],
                'insights' => $formatted['insights'] ?? [],
                'tables_used' => $generated['tables_used'],
            ];
        } catch (\Exception $e) {
            Log::error('SQL execution failed', [
                'sql' => $validation['sql'],
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Failed to execute query: '.$e->getMessage(),
                'sql' => $validation['sql'],
                'answer' => null,
                'html' => null,
            ];
        }
    }

    /**
     * Handle RAG-based queries
     */
    private function handleRagQuery(string $query): array
    {
        $chunks = $this->ragService->retrieveRelevantDocs($query, 5);

        if (empty($chunks)) {
            return [
                'success' => false,
                'error' => 'No relevant documentation found',
                'answer' => null,
                'html' => null,
                'sources' => [],
            ];
        }

        $answer = $this->ragService->answerFromContext($query, $chunks);

        return [
            'success' => true,
            'answer' => $answer,
            'html' => null,
            'visualization_type' => 'text',
            'sources' => array_column($chunks, 'source_file'),
            'insights' => [],
        ];
    }

    /**
     * Handle hybrid queries (SQL + RAG)
     * Returns a unified structure consistent with SQL and RAG handlers
     */
    private function handleHybridQuery(string $query): array
    {
        // Decompose the hybrid query into SQL and RAG components
        $decomposed = $this->decomposeHybridQuery($query);
        if (! $decomposed['success']) {
            // Fallback: use original query for both if decomposition fails
            Log::warning('Failed to decompose hybrid query, using original for both', [
                'query' => $query,
            ]);
            $sqlQuery = $query;
            $ragQuery = $query;
        } else {
            $sqlQuery = $decomposed['sql_query'];
            $ragQuery = $decomposed['rag_query'];
        }

        // Execute both queries with their specific sub-queries
        $sqlResult = $this->handleSqlQuery($sqlQuery);
        $ragResult = $this->handleRagQuery($ragQuery);

        // Check if both failed
        if (! $sqlResult['success'] && ! $ragResult['success']) {
            return [
                'success' => false,
                'error' => 'Both SQL and RAG queries failed',
                'answer' => null,
                'html' => null,
                'metadata' => [
                    'type' => 'hybrid',
                    'sql_error' => $sqlResult['error'] ?? null,
                    'rag_error' => $ragResult['error'] ?? null,
                ],
            ];
        }

        // Combine both results
        $combinedPrompt = $this->buildHybridPrompt(
            $query,
            $sqlQuery,
            $ragQuery,
            $sqlResult['answer'] ?? 'No data available',
            $ragResult['answer'] ?? 'No documentation available'
        );
        $finalAnswer = $this->geminiService->generateText($combinedPrompt);

        // Build unified response structure (consistent with handleSqlQuery)
        return [
            'success' => true,
            'answer' => $finalAnswer,

            // SQL-related fields (from sqlResult if available)
            'sql' => $sqlResult['sql'] ?? null,
            'html' => $sqlResult['html'] ?? null,
            'visualization_type' => $sqlResult['visualization_type'] ?? 'text',
            'insights' => $this->mergeInsights($sqlResult, $ragResult),
            'tables_used' => $sqlResult['tables_used'] ?? [],

            // RAG-related fields (from ragResult if available)
            'sources' => $ragResult['sources'] ?? [],

            // Hybrid-specific metadata
            'metadata' => [
                'type' => 'hybrid',
                'decomposed_queries' => [
                    'sql_query' => $sqlQuery,
                    'rag_query' => $ragQuery,
                ],
                'components' => [
                    'sql' => [
                        'success' => $sqlResult['success'],
                        'answer' => $sqlResult['answer'] ?? null,
                    ],
                    'rag' => [
                        'success' => $ragResult['success'],
                        'answer' => $ragResult['answer'] ?? null,
                    ],
                ],
            ],
        ];
    }

    /**
     * Merge insights from SQL and RAG results
     */
    private function mergeInsights(array $sqlResult, array $ragResult): array
    {
        $insights = [];

        // Add SQL insights if available
        if (! empty($sqlResult['insights'])) {
            $insights = array_merge($insights, $sqlResult['insights']);
        }

        // Add a note about documentation context if RAG succeeded
        if ($ragResult['success'] && ! empty($ragResult['sources'])) {
            $sourceCount = count($ragResult['sources']);
            $insights[] = "Enhanced with context from {$sourceCount} documentation source(s)";
        }

        return $insights;
    }

    /**
     * Decompose hybrid query into SQL and RAG components
     */
    private function decomposeHybridQuery(string $query): array
    {
        try {
            $prompt = <<<PROMPT
You are a query decomposition expert. The user has asked a question that requires BOTH:
1. Database query (to fetch/analyze data)
2. Documentation lookup (to explain concepts/policies/procedures)

Your task: Split the original query into TWO separate, focused sub-queries.

**Original Query:** "{$query}"

**Instructions:**
- Create a SQL-focused sub-query that asks for data retrieval, counting, filtering, or analysis
- Create a RAG-focused sub-query that asks for explanations, definitions, policies, or procedures
- Each sub-query should be a complete, standalone question
- Remove conjunctions like "and", "also", "plus", etc.
- Keep the intent clear and specific for each component

**Output Format (MUST be valid JSON):**
{
  "sql_query": "The data-focused question here",
  "rag_query": "The documentation-focused question here"
}

**Examples:**

Input: "Show me the top 10 users and explain our user verification policy"
Output:
{
  "sql_query": "Show me the top 10 users",
  "rag_query": "Explain our user verification policy"
}

Input: "How many orders were placed this month and what is the refund policy?"
Output:
{
  "sql_query": "How many orders were placed this month?",
  "rag_query": "What is the refund policy?"
}

Input: "List recent transactions with details about payment processing guidelines"
Output:
{
  "sql_query": "List recent transactions",
  "rag_query": "What are the payment processing guidelines?"
}

NOW DECOMPOSE THE QUERY. RESPOND WITH ONLY VALID JSON:
PROMPT;

            $response = $this->geminiService->generateText($prompt, [
                'temperature' => 0.2,
                'maxOutputTokens' => 200,
            ]);

            // Extract JSON from response
            if (preg_match('/\{[\s\S]*\}/s', $response, $matches)) {
                $decoded = json_decode($matches[0], true);

                if ($decoded && isset($decoded['sql_query']) && isset($decoded['rag_query'])) {
                    return [
                        'success' => true,
                        'sql_query' => trim($decoded['sql_query']),
                        'rag_query' => trim($decoded['rag_query']),
                    ];
                }
            }

            Log::warning('Failed to parse decomposed query JSON', [
                'response' => $response,
            ]);

            return ['success' => false];

        } catch (\Exception $e) {
            Log::error('Hybrid query decomposition failed', [
                'query' => $query,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false];
        }
    }

    /**
     * Format SQL result with NL answer and HTML visualization
     */
    private function formatSqlResult(string $query, array $data): array
    {
        if (empty($data)) {
            return [
                'answer' => 'No results found for your query.',
                'html' => null,
                'visualization_type' => 'text',
            ];
        }

        $rowCount = count($data);
        $dataStructure = $this->analyzeDataStructure($data);
        $dataSample = json_encode(array_slice($data, 0, 50)); // Increased sample size

        $prompt = <<<PROMPT
You are an intelligent data visualization assistant. Analyze the query result and provide:
1. A MANDATORY natural language answer (ALWAYS required)
2. Determine if HTML visualization would be helpful
3. If yes, generate complete HTML with inline CSS

### CRITICAL OUTPUT FORMAT RULES:
1. **ABSOLUTELY MUST** Return ONLY a valid JSON object.
2. **DO NOT** wrap the JSON in markdown code blocks (NO ```json or ```).
3. **DO NOT** include any text, greetings, apologies, or explanations *before or after* the JSON.
4. The JSON must be parseable directly.

User Question: "{$query}"

Data Info:
- **Total Rows (Full Dataset):** {$rowCount}
- **Columns/Schema:** {$dataStructure}

**Data Sample (FIRST 5 ROWS ONLY):**
{$dataSample}

RESPOND WITH VALID JSON ONLY:
{
  "answer": "MANDATORY: Natural language explanation of results",
  "needs_visualization": true/false,
  "visualization_type": "stats_card|table|bar_chart|line_chart|pie_chart|list|comparison|timeline|metric_grid|none",
  "html": "Complete HTML with inline CSS (if needs_visualization is true)",
  "insights": ["Key finding 1", "Key finding 2"],
  "reasoning": "Why this visualization type was chosen"
}
... (rest of the prompt remains the same)
PROMPT;

        $response = $this->geminiService->generateText($prompt, [
            'temperature' => 0.4,
            'maxOutputTokens' => 4096,
        ]);

        return $this->parseFormattedResponse($response, $data);
    }

    /**
     * Analyze data structure
     */
    private function analyzeDataStructure(array $data): string
    {
        if (empty($data)) {
            return 'No columns';
        }

        $firstRow = (array) $data[0];
        $columns = [];

        foreach ($firstRow as $column => $value) {
            $type = $this->detectColumnType($data, $column);
            $columns[] = "{$column} ({$type})";
        }

        return implode(', ', $columns);
    }

    /**
     * Detect column data type
     */
    private function detectColumnType(array $data, string $column): string
    {
        $sampleValues = array_slice(
            array_map(fn ($row) => is_object($row) ? $row->$column : $row[$column], $data),
            0,
            10
        );

        $hasDate = false;
        $hasNumeric = false;
        $hasText = false;

        foreach ($sampleValues as $value) {
            if (is_null($value)) {
                continue;
            }

            // Check if it's a date
            if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}/', $value)) {
                $hasDate = true;
            }

            // Check if it's numeric
            if (is_numeric($value)) {
                $hasNumeric = true;
            } else {
                $hasText = true;
            }
        }

        if ($hasDate) {
            return 'datetime';
        }
        if ($hasNumeric && ! $hasText) {
            return 'numeric';
        }
        if ($hasText) {
            return 'string';
        }

        return 'mixed';
    }

    /**
     * Parse AI response
     */
    private function parseFormattedResponse(?string $response, array $originalData): array
    {
        if (! $response) {
            return [
                'answer' => 'Unable to process the results.',
                'html' => null,
                'visualization_type' => 'text',
            ];
        }

        // Try to extract JSON from response
        if (preg_match('/\{[\s\S]*\}/s', $response, $matches)) {
            $decoded = json_decode($matches[0], true);

            if ($decoded && isset($decoded['answer'])) {
                return [
                    'answer' => $decoded['answer'],
                    'html' => ($decoded['needs_visualization'] ?? false) ? ($decoded['html'] ?? null) : null,
                    'visualization_type' => $decoded['visualization_type'] ?? 'text',
                    'insights' => $decoded['insights'] ?? [],
                    'reasoning' => $decoded['reasoning'] ?? null,
                ];
            }
        }

        // Fallback: Just return the response as text
        return [
            'answer' => $response,
            'html' => null,
            'visualization_type' => 'text',
        ];
    }

    /**
     * Build hybrid prompt combining SQL and RAG results
     */
    private function buildHybridPrompt(
        string $originalQuery,
        string $sqlQuery,
        string $ragQuery,
        string $sqlAnswer,
        string $ragAnswer
    ): string {
        return <<<PROMPT
You are synthesizing information from two sources to answer a complex user question.

**Original User Question:** "{$originalQuery}"

This question was intelligently decomposed into:

1. **Data Query (SQL):** "{$sqlQuery}"
   **Result:** {$sqlAnswer}

2. **Knowledge Query (Documentation):** "{$ragQuery}"
   **Result:** {$ragAnswer}

**Your Task:**
- Synthesize both results into ONE comprehensive, coherent answer
- Ensure the answer directly addresses the original question
- Integrate data findings with documentation context naturally
- Use clear, professional language
- If one component failed or has no data, focus on the successful component but mention what's missing

Provide a unified, well-structured answer:
PROMPT;
    }

    /**
     * Log query for analytics
     */
    private function logQuery(
        ?string $userId,
        string $query,
        string $intent,
        array $result,
        int $responseTime
    ): void {
        DB::table('query_logs')->insert([
            'user_id' => $userId,
            'query' => $query,
            'intent' => $intent,
            'generated_sql' => $result['sql'] ?? null,
            'retrieved_tables' => json_encode($result['tables_used'] ?? []),
            'result' => json_encode(['success' => $result['success']]),
            'response_time_ms' => $responseTime,
            'success' => $result['success'],
            'error_message' => $result['error'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
