<?php

declare(strict_types=1);

namespace Emon\LarabotAi\Console\Commands;

use Emon\LarabotAi\Services\GeminiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class EmbedSchemaCommand extends Command
{
    protected $signature = 'schema:embed';

    protected $description = 'Embed database schema with Gemini for AI-powered querying';

    public function __construct(private GeminiService $geminiService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Starting schema embedding process...');

        $tables = $this->getDatabaseTables();

        if (empty($tables)) {
            $this->error('No tables found in database');

            return self::FAILURE;
        }

        $this->info("Found {$tables->count()} tables to embed");

        $progressBar = $this->output->createProgressBar($tables->count());
        $progressBar->start();

        foreach ($tables as $tableName) {
            $this->embedTable($tableName);
            $progressBar->advance();

            // Rate limiting: Sleep 1 second between requests to stay under 60 RPM
            sleep(1);
        }

        $progressBar->finish();
        $this->newLine(2);
        $this->info('✅ Schema embedding completed successfully!');

        return self::SUCCESS;
    }

    private function getDatabaseTables(): \Illuminate\Support\Collection
    {
        $database = config('database.connections.mysql.database');

        return collect(DB::select('SHOW TABLES'))
            ->map(fn ($row) => array_values((array) $row)[0])
            ->filter(fn ($table) => ! in_array($table, [
                'migrations',
                'password_reset_tokens',
                'personal_access_tokens',
                'oauth_auth_codes',
                'oauth_access_tokens',
                'oauth_refresh_tokens',
                'oauth_clients',
                'oauth_device_codes',
                'cache',
                'cache_locks',
                'jobs',
                'job_batches',
                'failed_jobs',
                'sessions',
            ]));
    }

    private function embedTable(string $tableName): void
    {
        // Get column information
        $columns = collect(DB::select("SHOW FULL COLUMNS FROM `{$tableName}`"));

        // Get foreign keys (relationships)
        $foreignKeys = collect(DB::select('
            SELECT
                COLUMN_NAME,
                REFERENCED_TABLE_NAME,
                REFERENCED_COLUMN_NAME
            FROM
                INFORMATION_SCHEMA.KEY_COLUMN_USAGE
            WHERE
                TABLE_SCHEMA = ? AND
                TABLE_NAME = ? AND
                REFERENCED_TABLE_NAME IS NOT NULL
        ', [config('database.connections.mysql.database'), $tableName]));

        // Build column list for summary
        $columnList = $columns->map(function ($col) {
            return sprintf(
                '%s (%s)%s',
                $col->Field,
                $col->Type,
                $col->Null === 'NO' ? ' NOT NULL' : ''
            );
        })->implode(', ');

        // Build relationships summary
        $relationships = $foreignKeys->map(function ($fk) {
            return [
                'column' => $fk->COLUMN_NAME,
                'references_table' => $fk->REFERENCED_TABLE_NAME,
                'references_column' => $fk->REFERENCED_COLUMN_NAME,
            ];
        })->toArray();

        // Create comprehensive summary for embedding
        $summary = "Table: {$tableName}\n";
        $summary .= "Columns: {$columnList}\n";
        $summary .= "Purpose: Stores {$tableName} related data.";

        if (! empty($relationships)) {
            $relationshipsText = collect($relationships)
                ->map(fn ($r) => "{$r['column']} → {$r['references_table']}.{$r['references_column']}")
                ->implode(', ');
            $summary .= "\nRelationships: {$relationshipsText}";
        }

        // Generate embedding
        $embedding = $this->geminiService->generateEmbedding($summary);

        if ($embedding === null) {
            $this->warn("\n⚠️  Failed to generate embedding for {$tableName}");

            return;
        }

        // Store in database
        DB::table('schema_embeddings')->updateOrInsert(
            ['table_name' => $tableName],
            [
                'columns' => json_encode($columns->toArray()),
                'summary' => $summary,
                'relationships' => json_encode($relationships),
                'embedding' => json_encode($embedding),
                'updated_at' => now(),
                'created_at' => DB::raw('COALESCE(created_at, NOW())'),
            ]
        );
    }
}
