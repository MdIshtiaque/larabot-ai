<?php

declare(strict_types=1);

namespace Emon\LarabotAi\Services;

use Illuminate\Support\Facades\DB;

class SchemaRetrievalService
{
    public function __construct(private GeminiService $geminiService) {}

    /**
     * Retrieve relevant tables based on query similarity + keyword matching
     */
    public function retrieveRelevantTables(string $query, int $limit = 5): array
    {
        // Get all schema embeddings
        $schemas = DB::table('schema_embeddings')->get();

        if ($schemas->isEmpty()) {
            return [];
        }

        // Step 1: Find tables mentioned directly in the query (keyword matching)
        $mentionedTables = $this->findMentionedTables($query, $schemas);

        // Step 2: Add related tables via foreign keys
        $relatedTables = $this->findRelatedTables($mentionedTables, $schemas);

        // Step 3: Generate embedding for semantic search
        $queryEmbedding = $this->geminiService->generateEmbedding($query);

        // Step 4: Calculate similarity scores for all tables
        $allResults = $schemas->map(function ($schema) use ($queryEmbedding, $mentionedTables, $relatedTables) {
            $schemaEmbedding = json_decode($schema->embedding, true);
            $similarity = $queryEmbedding ? $this->geminiService->cosineSimilarity($queryEmbedding, $schemaEmbedding) : 0;

            // Boost score if table is mentioned in query
            if (in_array($schema->table_name, $mentionedTables)) {
                $similarity += 0.5; // Strong boost for direct mentions
            }

            // Boost score if table is related to mentioned tables
            if (in_array($schema->table_name, $relatedTables)) {
                $similarity += 0.3; // Moderate boost for related tables
            }

            return [
                'table_name' => $schema->table_name,
                'summary' => $schema->summary,
                'columns' => json_decode($schema->columns, true),
                'relationships' => json_decode($schema->relationships, true),
                'similarity' => $similarity,
            ];
        })
            ->sortByDesc('similarity')
            ->take($limit)
            ->values()
            ->toArray();

        return $allResults;
    }

    /**
     * Find tables mentioned directly in the query (table names OR column names)
     */
    private function findMentionedTables(string $query, $schemas): array
    {
        $queryLower = strtolower($query);
        $mentioned = [];

        foreach ($schemas as $schema) {
            $tableName = $schema->table_name;
            $tableNameSingular = rtrim($tableName, 's'); // Simple singularization

            // Check if table name (or singular form) is mentioned in query
            if (
                str_contains($queryLower, strtolower($tableName)) ||
                str_contains($queryLower, strtolower($tableNameSingular))
            ) {
                $mentioned[] = $tableName;

                continue;
            }

            // IMPORTANT: Also check if query mentions any COLUMN names from this table
            $columns = json_decode($schema->columns, true) ?? [];
            foreach ($columns as $column) {
                $columnName = strtolower($column['Field']);

                // Handle multi-word column names (e.g., "meal_rate" or "meal rate")
                $columnNameWithSpace = str_replace('_', ' ', $columnName);

                if (
                    str_contains($queryLower, $columnName) ||
                    str_contains($queryLower, $columnNameWithSpace)
                ) {
                    $mentioned[] = $tableName;
                    break; // Found a match, no need to check other columns
                }
            }
        }

        return array_unique($mentioned);
    }

    /**
     * Find tables related to mentioned tables via foreign keys
     */
    private function findRelatedTables(array $mentionedTables, $schemas): array
    {
        $related = [];

        foreach ($mentionedTables as $tableName) {
            $schema = $schemas->firstWhere('table_name', $tableName);

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

    /**
     * Format retrieved tables for SQL generation prompt
     */
    public function formatForPrompt(array $tables): string
    {
        $formatted = "Available database tables and their structure:\n\n";

        foreach ($tables as $table) {
            $formatted .= "Table: {$table['table_name']}\n";

            // Format columns (columns are arrays, not objects)
            $columns = collect($table['columns'])
                ->map(fn ($col) => "  - {$col['Field']} ({$col['Type']})")
                ->implode("\n");

            $formatted .= "Columns:\n{$columns}\n";

            // Add relationships if any
            if (! empty($table['relationships'])) {
                $relationships = collect($table['relationships'])
                    ->map(fn ($rel) => "  - {$rel['column']} → {$rel['references_table']}.{$rel['references_column']}")
                    ->implode("\n");

                $formatted .= "Foreign Keys:\n{$relationships}\n";
            }

            $formatted .= "\n";
        }

        return $formatted;
    }

    /**
     * Get table names from retrieved results
     */
    public function getTableNames(array $tables): array
    {
        return array_column($tables, 'table_name');
    }
}
