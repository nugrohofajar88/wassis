<?php

namespace App\Services\AI;

use App\Services\AI\Prompts\MemoryExtractionPrompt;
use App\Services\AI\Prompts\StyleAnalysisPrompt;
use App\Services\AI\Prompts\ReplyGenerationPrompt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiProvider implements AIProviderInterface
{
    protected string $apiKey;
    protected string $model;
    protected string $endpoint;

    public function __construct(string $apiKey, string $model, string $endpoint)
    {
        $this->apiKey   = $apiKey;
        $this->model    = $model;
        $this->endpoint = rtrim($endpoint, '/');
    }

    public function extractMemories(string $conversation, array $context = []): array
    {
        $response = $this->chat(
            MemoryExtractionPrompt::system($context),
            MemoryExtractionPrompt::user($conversation)
        );

        // Strip possible markdown code fences
        $clean   = preg_replace('/^```(?:json)?\s*|\s*```$/s', '', trim($response));
        $decoded = json_decode($clean, true);

        if (! is_array($decoded)) {
            Log::warning('Gemini: Failed to parse memory extraction response', ['raw' => $response]);
            return [];
        }

        return $decoded;
    }

    public function analyzeStyle(string $conversation): array
    {
        $response = $this->chat(
            StyleAnalysisPrompt::system(),
            StyleAnalysisPrompt::user($conversation)
        );

        $clean   = preg_replace('/^```(?:json)?\s*|\s*```$/s', '', trim($response));
        $decoded = json_decode($clean, true);

        if (! is_array($decoded)) {
            Log::warning('Gemini: Failed to parse style analysis response', ['raw' => $response]);
            return [];
        }

        return $decoded;
    }

    public function generateReply(
        string $conversation,
        array $styleProfile = [],
        array $memories = [],
        string $instruction = ''
    ): string {
        return $this->chat(
            ReplyGenerationPrompt::system($styleProfile, $memories),
            ReplyGenerationPrompt::user($conversation, $instruction)
        );
    }

    public function generateAutoReply(
        string $conversation,
        array $styleProfile = [],
        array $memories = []
    ): array {
        $response = $this->chat(
            ReplyGenerationPrompt::systemForAutoReply($styleProfile, $memories),
            ReplyGenerationPrompt::user($conversation)
        );

        $clean   = preg_replace('/^```(?:json)?\s*|\s*```$/s', '', trim($response));
        $decoded = json_decode($clean, true);

        if (! is_array($decoded) || ! array_key_exists('needs_reply', $decoded)) {
            Log::warning('Gemini: Failed to parse auto-reply decision response', ['raw' => $response]);
            return ['needs_reply' => false, 'reply' => ''];
        }

        return [
            'needs_reply' => (bool) $decoded['needs_reply'],
            'reply'       => (string) ($decoded['reply'] ?? ''),
        ];
    }

    public function chat(string $systemPrompt, string $userMessage): string
    {
        try {
            $url = "{$this->endpoint}/models/{$this->model}:generateContent?key={$this->apiKey}";

            $response = Http::post($url, [
                'system_instruction' => [
                    'parts' => [['text' => $systemPrompt]],
                ],
                'contents' => [
                    ['role' => 'user', 'parts' => [['text' => $userMessage]]],
                ],
                'generationConfig' => [
                    'temperature' => 0.7,
                ],
            ]);

            if ($response->failed()) {
                Log::error('Gemini API Error', ['status' => $response->status(), 'body' => $response->body()]);
                return '';
            }

            return $response->json('candidates.0.content.parts.0.text', '');
        } catch (\Exception $e) {
            Log::error('Gemini Exception: ' . $e->getMessage());
            return '';
        }
    }
}
