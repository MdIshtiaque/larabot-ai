<?php

declare(strict_types=1);

namespace Emon\LarabotAi\Http\Controllers;


use Emon\LarabotAi\Services\HybridBotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BotController
{
    public function __construct(private HybridBotService $botService) {}

    /**
     * Ask a question to the AI bot
     */
    public function ask(Request $request): JsonResponse
    {
        $request->validate([
            'query' => 'required|string|max:500',
        ]);

        try {
            $userId = $request->user()?->id;
            $result = $this->botService->ask($request->input('query'), $userId);
            return response()->json([
                'success' => $result['success'],
                'data' => [
                    'answer' => $result['answer'] ?? null,
                    'intent' => $result['intent'],
                    'response_time_ms' => $result['response_time_ms'],
                    'sql' => $result['sql'] ?? null,
                    'sources' => $result['sources'] ?? null,
                ],
                'error' => $result['error'] ?? null,
            ], $result['success'] ? 200 : 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while processing your query',
                'error' => app()->environment('local') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Get query history for authenticated user
     */
    public function history(Request $request): JsonResponse
    {
        $userId = $request->user()?->id;

        if (! $userId) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        $logs = \DB::table('query_logs')
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $logs,
        ]);
    }

    /**
     * Get bot statistics (admin only)
     */
    public function stats(): JsonResponse
    {
        $stats = [
            'total_queries' => \DB::table('query_logs')->count(),
            'successful_queries' => \DB::table('query_logs')->where('success', true)->count(),
            'failed_queries' => \DB::table('query_logs')->where('success', false)->count(),
            'avg_response_time' => \DB::table('query_logs')->avg('response_time_ms'),
            'intent_breakdown' => \DB::table('query_logs')
                ->selectRaw('intent, COUNT(*) as count')
                ->groupBy('intent')
                ->get(),
            'schema_embeddings_count' => \DB::table('schema_embeddings')->count(),
            'knowledge_chunks_count' => \DB::table('knowledge_chunks')->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }
}
