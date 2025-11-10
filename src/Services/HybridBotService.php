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
     * Detect query intent (sql, rag, or hybrid)
     */
    private function detectIntent(string $query): string
    {
        $sqlKeywords = [
            // Counting & Aggregation
            'how many', 'count', 'total', 'sum', 'average', 'mean', 'median', 'min', 'minimum',
            'max', 'maximum', 'aggregate', 'statistics', 'stat',
            
            // Listing & Display
            'list all', 'show me', 'display', 'get all', 'fetch', 'retrieve', 'select',
            'give me', 'bring up', 'pull up',
            
            // Filtering & Search
            'who are', 'which', 'find', 'search for', 'filter', 'where', 'lookup',
            'match', 'contains', 'like',
            
            // Ordering & Ranking
            'last', 'recent', 'latest', 'newest', 'oldest', 'first', 'top', 'bottom',
            'highest', 'lowest', 'best', 'worst', 'order by', 'sort by', 'rank',
            
            // Comparison & Range
            'between', 'greater than', 'less than', 'more than', 'fewer than',
            'above', 'below', 'over', 'under', 'at least', 'at most',
            
            // Calculation & Math
            'calculate', 'compute', 'divide', 'multiply', 'percentage', 'percent',
            'ratio', 'proportion', 'growth', 'increase', 'decrease',
            
            // Time-based
            'today', 'yesterday', 'this week', 'this month', 'this year', 'last week',
            'last month', 'last year', 'daily', 'weekly', 'monthly', 'yearly',
            
            // Grouping & Distribution
            'group by', 'grouped by', 'per', 'by category', 'breakdown', 'distribution',
            'each', 'every', 'for each',
            
            // Existence & Boolean
            'exists', 'does', 'has', 'have', 'is there', 'are there', 'any',
            'all', 'none', 'without',
        ];

        $ragKeywords = [
            // Question Starters
            'what is', 'what are', 'what does', 'tell me', 'tell me about',
            'help me understand', 'i need to know',
            
            // Explanation & Description
            'explain', 'describe', 'clarify', 'elaborate', 'detail', 'details',
            'illustrate', 'demonstrate',
            
            // How-to & Procedures
            'how to', 'how do i', 'how can i', 'how should i', 'steps to',
            'way to', 'method to', 'process', 'procedure', 'workflow',
            
            // Permission & Capability
            'can i', 'could i', 'may i', 'am i allowed', 'is it possible',
            'should i', 'would it be',
            
            // Policy & Rules
            'policy', 'policies', 'rule', 'rules', 'guideline', 'guidelines',
            'regulation', 'requirement', 'requirements', 'standard', 'standards',
            
            // Definition & Meaning
            'definition', 'meaning', 'means', 'refers to', 'stands for',
            'defined as', 'concept', 'idea', 'term',
            
            // Reasoning & Understanding
            'why', 'why is', 'why does', 'reason', 'because', 'purpose',
            'benefit', 'advantage', 'rationale',
            
            // Timing & Conditions
            'when', 'when should', 'when to', 'when can', 'when is',
            'under what', 'condition', 'prerequisite', 'before',
            
            // Comparison & Differences
            'difference between', 'compare', 'comparison', 'versus', 'vs',
            'distinguish', 'differentiate', 'contrast', 'similar', 'same as',
            
            // Documentation & Guidance
            'documentation', 'document', 'guide', 'tutorial', 'instruction',
            'instructions', 'manual', 'reference', 'best practice', 'example',
            'examples', 'use case', 'scenario',
        ];

        $hybridKeywords = [
            // Conjunctive Phrases
            'and what', 'and also', 'also explain', 'also tell', 'also show',
            'and explain', 'and describe', 'and why',
            
            // Addition Phrases
            'plus policy', 'plus rule', 'plus guideline', 'plus documentation',
            'along with', 'together with', 'combined with', 'as well as',
            
            // Detail Phrases
            'with details about', 'with explanation', 'with context',
            'including', 'including why', 'including how',
            
            // Context Phrases
            'in context of', 'regarding', 'with respect to', 'in relation to',
            'in terms of', 'concerning',
            
            // Sequential Phrases
            'then explain', 'then tell', 'then describe', 'after that',
            'followed by', 'and additionally',
        ];

        $queryLower = strtolower($query);

        // Check for hybrid indicators
        foreach ($hybridKeywords as $keyword) {
            if (str_contains($queryLower, $keyword)) {
                return 'hybrid';
            }
        }

        // Count matches for SQL vs RAG
        $sqlScore = 0;
        $ragScore = 0;

        foreach ($sqlKeywords as $keyword) {
            if (str_contains($queryLower, $keyword)) {
                $sqlScore++;
            }
        }

        foreach ($ragKeywords as $keyword) {
            if (str_contains($queryLower, $keyword)) {
                $ragScore++;
            }
        }

        // Return intent based on scores
        if ($sqlScore > $ragScore) {
            return 'sql';
        } elseif ($ragScore > $sqlScore) {
            return 'rag';
        }

        // Default: try RAG first for ambiguous queries
        return 'rag';
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
            ];
        }

        $answer = $this->ragService->answerFromContext($query, $chunks);

        return [
            'success' => true,
            'answer' => $answer,
            'sources' => array_column($chunks, 'source_file'),
        ];
    }

    /**
     * Handle hybrid queries (SQL + RAG)
     */
    private function handleHybridQuery(string $query): array
    {
        $sqlResult = $this->handleSqlQuery($query);
        $ragResult = $this->handleRagQuery($query);

        // Combine both results
        $combinedPrompt = $this->buildHybridPrompt(
            $query,
            $sqlResult['answer'] ?? 'No data available',
            $ragResult['answer'] ?? 'No documentation available'
        );

        $finalAnswer = $this->geminiService->generateText($combinedPrompt);

        return [
            'success' => true,
            'answer' => $finalAnswer,
            'sql_result' => $sqlResult,
            'rag_result' => $ragResult,
        ];
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
            array_map(fn($row) => is_object($row) ? $row->$column : $row[$column], $data),
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
    private function buildHybridPrompt(string $query, string $sqlAnswer, string $ragAnswer): string
    {
        return <<<PROMPT
Combine the following information to provide a comprehensive answer to the user's question.

User Question: "{$query}"

Data Analysis Result:
{$sqlAnswer}

Documentation/Policy Information:
{$ragAnswer}

Provide a unified, clear answer that incorporates both the data analysis and documentation information.
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

