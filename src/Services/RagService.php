<?php

declare(strict_types=1);

namespace Emon\LarabotAi\Services;

use Illuminate\Support\Facades\DB;

class RagService
{
    public function __construct(private GeminiService $geminiService) {}

    /**
     * Retrieve relevant documentation chunks based on query
     */
    public function retrieveRelevantDocs(string $query, int $limit = 5): array
    {
        // Generate embedding for query
        $queryEmbedding = $this->geminiService->generateEmbedding($query);

        if ($queryEmbedding === null) {
            return [];
        }

        // Get all knowledge chunks
        $chunks = DB::table('knowledge_chunks')->get();

        if ($chunks->isEmpty()) {
            return [];
        }

        // Calculate similarity scores
        $results = $chunks->map(function ($chunk) use ($queryEmbedding) {
            $chunkEmbedding = json_decode($chunk->embedding, true);
            $similarity = $this->geminiService->cosineSimilarity($queryEmbedding, $chunkEmbedding);

            return [
                'content' => $chunk->content,
                'source_file' => $chunk->source_file,
                'metadata' => json_decode($chunk->metadata, true),
                'similarity' => $similarity,
            ];
        })
            ->sortByDesc('similarity')
            ->take($limit)
            ->values()
            ->toArray();

        return $results;
    }

    /**
     * Answer question using retrieved context
     */
    public function answerFromContext(string $query, array $chunks): ?string
    {
        if (empty($chunks)) {
            return null;
        }

        // Build context from chunks
        $context = collect($chunks)
            ->map(fn ($chunk, $idx) => ($idx + 1).". {$chunk['content']}")
            ->implode("\n\n");

        // Build prompt
        $prompt = $this->buildRagPrompt($query, $context);

        // Generate answer
        return $this->geminiService->generateText($prompt, [
            'temperature' => 0.3,
            'maxOutputTokens' => 1024,
        ]);
    }

    /**
     * Build RAG prompt
     */
    private function buildRagPrompt(string $query, string $context): string
    {
        return <<<PROMPT
You are a knowledge assistant. Answer the user's question using ONLY the provided context below.

Context:
{$context}

Rules:
1. Answer based ONLY on the context provided
2. If the context doesn't contain relevant information, say "I don't have enough information to answer this question."
3. Be concise and direct
4. Cite sources when possible (mention the document name)
5. Don't make up information

User Question: "{$query}"

Answer:
PROMPT;
    }

    /**
     * Format chunks for display
     */
    public function formatChunks(array $chunks): string
    {
        return collect($chunks)
            ->map(function ($chunk, $idx) {
                $source = basename($chunk['source_file']);
                $similarity = round($chunk['similarity'] * 100, 1);

                return sprintf(
                    "[%d] %s (similarity: %s%%)\n%s",
                    $idx + 1,
                    $source,
                    $similarity,
                    $chunk['content']
                );
            })
            ->implode("\n\n");
    }
}

