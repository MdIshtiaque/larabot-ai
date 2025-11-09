<?php

declare(strict_types=1);

namespace Emon\LarabotAi\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class BotRateLimitMiddleware
{
    /**
     * Handle an incoming request - limit bot queries per user
     */
    public function handle(Request $request, Closure $next): Response
    {
        $userId = $request->user()?->id ?? $request->ip();

        $executed = RateLimiter::attempt("bot-query:{$userId}", 10, fn () => true);

        // Add query length validation
        $query = $request->input('query', '');
        if (strlen($query) > 500) {
            return response()->json([
                'success' => false,
                'message' => 'Query too long. Maximum 500 characters allowed.',
            ], 422);
        }

        // Check for suspicious patterns
        $suspiciousPatterns = [
            '/\b(DROP|DELETE|UPDATE|INSERT|TRUNCATE|ALTER|CREATE)\b/i',
            '/--|\#|\/\*/', // SQL comments
            '/;\s*\w+/', // Multiple statements
        ];

        foreach ($suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $query)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Query contains suspicious patterns.',
                ], 400);
            }
        }

        return $next($request);
    }
}
