<?php

declare(strict_types=1);

namespace Emon\LarabotAi\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    private Client $client;

    public function __construct()
    {
        $this->client = new Client([
            'base_uri' => config('gemini.base_url'),
            'timeout' => config('gemini.timeout'),
            'headers' => [
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    /**
     * Generate embedding for given text using Gemini embedding model
     */
    public function generateEmbedding(string $text): ?array
    {
        try {
            $url = config('gemini.embed_model').':embedContent?key='.config('gemini.api_key');

            $response = $this->client->post($url, [
                'json' => [
                    'model' => config('gemini.embed_model'),
                    'content' => [
                        'parts' => [
                            ['text' => $text],
                        ],
                    ],
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            return $data['embedding']['values'] ?? null;
        } catch (GuzzleException $e) {
            $errorMessage = $e->getMessage();

            // Check if it's a rate limit error (429)
            if (str_contains($errorMessage, '429') || str_contains($errorMessage, 'quota')) {
                Log::warning('Gemini rate limit hit, waiting 5 seconds...', [
                    'text_length' => strlen($text),
                ]);
                sleep(5); // Wait 5 seconds on rate limit
            }

            Log::error('Gemini embedding failed', [
                'error' => $errorMessage,
                'text_length' => strlen($text),
            ]);

            return null;
        }
    }

    /**
     * Generate text response from Gemini LLM
     */
    public function generateText(string $prompt, array $options = []): ?string
    {
        try {
            $url = config('gemini.llm_model').':generateContent?key='.config('gemini.api_key');

            $response = $this->client->post($url, [
                'json' => [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => $prompt],
                            ],
                        ],
                    ],
                    'generationConfig' => array_merge([
                        'temperature' => 0.2,
                        'maxOutputTokens' => 2048,
                    ], $options),
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            return $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
        } catch (GuzzleException $e) {
            Log::error('Gemini text generation failed', [
                'error' => $e->getMessage(),
                'prompt_length' => strlen($prompt),
            ]);

            return null;
        }
    }

    /**
     * Calculate cosine similarity between two vectors
     */
    public function cosineSimilarity(array $vecA, array $vecB): float
    {
        if (count($vecA) !== count($vecB)) {
            return 0.0;
        }

        $dotProduct = 0.0;
        $magnitudeA = 0.0;
        $magnitudeB = 0.0;

        for ($i = 0; $i < count($vecA); $i++) {
            $dotProduct += $vecA[$i] * $vecB[$i];
            $magnitudeA += $vecA[$i] * $vecA[$i];
            $magnitudeB += $vecB[$i] * $vecB[$i];
        }

        $magnitudeA = sqrt($magnitudeA);
        $magnitudeB = sqrt($magnitudeB);

        if ($magnitudeA == 0 || $magnitudeB == 0) {
            return 0.0;
        }

        return $dotProduct / ($magnitudeA * $magnitudeB);
    }
}
