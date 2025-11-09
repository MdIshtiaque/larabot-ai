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
            'how many', 'count', 'total', 'sum', 'average', 'list all', 'show me',
            'who are', 'which', 'find', 'search for', 'get all', 'last', 'recent',
            'top', 'highest', 'lowest', 'between', 'calculate',
        ];

        $ragKeywords = [
            'what is', 'what are', 'explain', 'describe', 'how to', 'can i',
            'policy', 'rule', 'guideline', 'definition', 'meaning', 'why',
            'when should', 'difference between',
        ];

        $hybridKeywords = [
            'and what', 'also explain', 'plus policy', 'with details about',
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
            ];
        }

        // Execute SQL on read-only connection
        try {
            $data = DB::connection('mysql_readonly')->select($validation['sql']);

            // Format result as natural language
            $answer = $this->formatSqlResult($query, $data);

            return [
                'success' => true,
                'sql' => $validation['sql'],
                'data' => $data,
                'answer' => $answer,
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
     * Format SQL result as natural language
     */
    private function formatSqlResult(string $query, array $data): string
    {
        if (empty($data)) {
            return 'No results found for your query.';
        }

        $dataJson = json_encode(array_slice($data, 0, 10)); // Limit to 10 rows for context

        $prompt = <<<PROMPT
Convert the following SQL query result into a natural language answer.

User Question: "{$query}"

Data (JSON):
{$dataJson}

Provide a clear, concise answer based on this data. If there are multiple rows, summarize them appropriately.
PROMPT;

        return $this->geminiService->generateText($prompt, ['temperature' => 0.3]) ?? json_encode($data);
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

