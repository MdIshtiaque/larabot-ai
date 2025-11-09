<?php

declare(strict_types=1);

namespace Emon\LarabotAi\Console\Commands;

use Emon\LarabotAi\Services\GeminiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class EmbedSchemaCommand extends Command
{
    protected $signature = 'schema:embed';

    protected $description = 'Interactively select and embed database tables with Gemini for AI-powered querying';

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

        // Show available tables
        $this->info("Found {$tables->count()} tables in the database:");
        $this->newLine();

        $selectedTables = $this->selectTables($tables);

        if (empty($selectedTables)) {
            $this->warn('No tables selected. Aborting...');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info("Embedding " . count($selectedTables) . " table(s)...");

        $progressBar = $this->output->createProgressBar(count($selectedTables));
        $progressBar->start();

        foreach ($selectedTables as $tableName) {
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

    private function selectTables(\Illuminate\Support\Collection $tables): array
    {
        // Display tables with checkboxes
        $tableArray = $tables->values()->toArray();
        $choices = [];

        foreach ($tableArray as $index => $table) {
            $choices[] = sprintf('[%d] %s', $index + 1, $table);
        }

        // Display all tables
        foreach ($choices as $choice) {
            $this->line("  {$choice}");
        }

        $this->newLine();
        $this->comment('Select tables to embed:');
        $this->comment('  • Enter numbers separated by comma (e.g., 1,3,5)');
        $this->comment('  • Enter "all" to select all tables');
        $this->comment('  • Enter ranges with dash (e.g., 1-5 or 1,3,5-8)');
        $this->comment('  • Press Enter with no input to cancel');
        $this->newLine();

        $selection = $this->ask('Your selection');

        // Handle empty selection
        if (empty($selection)) {
            return [];
        }

        // Handle "all" selection
        if (strtolower(trim($selection)) === 'all') {
            return $tableArray;
        }

        // Parse selection
        $selectedIndices = $this->parseSelection($selection, count($tableArray));

        if (empty($selectedIndices)) {
            $this->error('Invalid selection. Please try again.');

            return $this->selectTables($tables);
        }

        // Get selected tables
        $selectedTables = [];
        foreach ($selectedIndices as $index) {
            if (isset($tableArray[$index - 1])) {
                $selectedTables[] = $tableArray[$index - 1];
            }
        }

        // Confirm selection
        $this->newLine();
        $this->info('Selected tables:');
        foreach ($selectedTables as $table) {
            $this->line("  ✓ {$table}");
        }
        $this->newLine();

        if (! $this->confirm('Proceed with these tables?', true)) {
            return $this->selectTables($tables);
        }

        return $selectedTables;
    }

    private function parseSelection(string $selection, int $maxIndex): array
    {
        $indices = [];
        $parts = explode(',', $selection);

        foreach ($parts as $part) {
            $part = trim($part);

            // Handle ranges (e.g., "1-5")
            if (str_contains($part, '-')) {
                [$start, $end] = array_map('intval', explode('-', $part, 2));

                if ($start > 0 && $end <= $maxIndex && $start <= $end) {
                    for ($i = $start; $i <= $end; $i++) {
                        $indices[] = $i;
                    }
                }
            } elseif (is_numeric($part)) {
                $index = (int) $part;
                if ($index > 0 && $index <= $maxIndex) {
                    $indices[] = $index;
                }
            }
        }

        return array_unique($indices);
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
        // Get table comment
        $tableInfo = DB::select("
            SELECT TABLE_COMMENT 
            FROM INFORMATION_SCHEMA.TABLES 
            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
        ", [config('database.connections.mysql.database'), $tableName]);
        
        $tableComment = $tableInfo[0]->TABLE_COMMENT ?? '';
        
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
            $info = sprintf(
                '%s (%s)%s',
                $col->Field,
                $col->Type,
                $col->Null === 'NO' ? ' NOT NULL' : ''
            );
            
            // Add column comment if available
            if (! empty($col->Comment)) {
                $info .= " - {$col->Comment}";
            }
            
            return $info;
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
        
        // Add table comment if available
        if (! empty($tableComment)) {
            $summary .= "Description: {$tableComment}\n";
        }
        
        $summary .= "Columns: {$columnList}\n";
        
        // Add generic purpose only if no table comment exists
        if (empty($tableComment)) {
            $summary .= "Purpose: Stores {$tableName} related data.";
        }

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
