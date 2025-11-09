<?php

declare(strict_types=1);

namespace Emon\LarabotAi\Console\Commands;

use Emon\LarabotAi\Services\GeminiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class EmbedDocsCommand extends Command
{
    protected $signature = 'docs:embed {--dir=docs : Directory containing documentation files}';

    protected $description = 'Embed documentation files for RAG-based answering';

    public function __construct(private GeminiService $geminiService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $docsDir = base_path($this->option('dir'));

        if (! File::isDirectory($docsDir)) {
            $this->error("Directory not found: {$docsDir}");

            return self::FAILURE;
        }

        $this->info('Starting documentation embedding process...');

        $files = File::allFiles($docsDir);
        $markdownFiles = collect($files)->filter(fn ($file) => $file->getExtension() === 'md');

        if ($markdownFiles->isEmpty()) {
            $this->warn('No markdown files found');

            return self::SUCCESS;
        }

        $this->info("Found {$markdownFiles->count()} markdown files");

        $progressBar = $this->output->createProgressBar($markdownFiles->count());
        $progressBar->start();

        foreach ($markdownFiles as $file) {
            $this->embedFile($file);
            $progressBar->advance();
            
            // Rate limiting: Sleep 2 seconds between files (docs have multiple chunks)
            sleep(2);
        }

        $progressBar->finish();
        $this->newLine(2);
        $this->info('✅ Documentation embedding completed!');

        return self::SUCCESS;
    }

    private function embedFile(\SplFileInfo $file): void
    {
        $content = File::get($file->getPathname());
        $relativePath = str_replace(base_path().'/', '', $file->getPathname());

        // Chunk large documents (split by sections/headings)
        $chunks = $this->chunkDocument($content);

        foreach ($chunks as $idx => $chunk) {
            if (strlen(trim($chunk)) < 50) {
                continue; // Skip very small chunks
            }

            $embedding = $this->geminiService->generateEmbedding($chunk);

            if ($embedding === null) {
                $this->warn("\n⚠️  Failed to embed chunk from {$file->getFilename()}");

                continue;
            }

            DB::table('knowledge_chunks')->insert([
                'source_file' => $relativePath,
                'source_type' => 'markdown',
                'content' => $chunk,
                'metadata' => json_encode([
                    'filename' => $file->getFilename(),
                    'chunk_index' => $idx,
                    'size' => strlen($chunk),
                ]),
                'embedding' => json_encode($embedding),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            // Rate limiting: Sleep 0.7 seconds per chunk to stay under 80 RPM
            usleep(700000); // 700ms
        }
    }

    /**
     * Split document into chunks by markdown headers
     */
    private function chunkDocument(string $content): array
    {
        // Split by markdown headers (##, ###, etc)
        $chunks = preg_split('/(?=^#{1,3}\s)/m', $content);

        return array_filter($chunks, fn ($chunk) => ! empty(trim($chunk)));
    }
}

