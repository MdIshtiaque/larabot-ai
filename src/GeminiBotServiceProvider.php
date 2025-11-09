<?php

declare(strict_types=1);

namespace Emon\LarabotAi;

use Emon\LarabotAi\Console\Commands\EmbedDocsCommand;
use Emon\LarabotAi\Console\Commands\EmbedSchemaCommand;
use Emon\LarabotAi\Http\Middleware\BotRateLimitMiddleware;
use Emon\LarabotAi\Services\GeminiService;
use Emon\LarabotAi\Services\HybridBotService;
use Emon\LarabotAi\Services\RagService;
use Emon\LarabotAi\Services\SchemaRetrievalService;
use Emon\LarabotAi\Services\SqlGenerationService;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

class LarabotAiServiceProvider extends ServiceProvider
{
    /**
     * Register services
     */
    public function register(): void
    {
        // Merge config
        $this->mergeConfigFrom(__DIR__.'/Config/gemini.php', 'gemini');

        // Register services as singletons
        $this->app->singleton(GeminiService::class);
        $this->app->singleton(SchemaRetrievalService::class);
        $this->app->singleton(SqlGenerationService::class);
        $this->app->singleton(RagService::class);
        $this->app->singleton(HybridBotService::class);
    }

    /**
     * Bootstrap services
     */
    public function boot(): void
    {
        // Publish config
        $this->publishes([
            __DIR__.'/Config/gemini.php' => config_path('gemini.php'),
        ], 'larabot-config');

        // Publish migrations
        $this->publishes([
            __DIR__.'/Database/Migrations/create_schema_embeddings_table.php' => database_path('migrations/'.date('Y_m_d_His', time()).'_create_schema_embeddings_table.php'),
            __DIR__.'/Database/Migrations/create_knowledge_chunks_table.php' => database_path('migrations/'.date('Y_m_d_His', time() + 1).'_create_knowledge_chunks_table.php'),
            __DIR__.'/Database/Migrations/create_query_logs_table.php' => database_path('migrations/'.date('Y_m_d_His', time() + 2).'_create_query_logs_table.php'),
        ], 'larabot-migrations');

        // Load routes automatically
        $this->loadRoutesFrom(__DIR__.'/routes/api.php');

        // Register middleware
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('bot.rate-limit', BotRateLimitMiddleware::class);

        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                EmbedSchemaCommand::class,
                EmbedDocsCommand::class,
            ]);
        }
    }
}

