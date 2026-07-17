<?php

namespace App\Services\AI;

use App\Services\AI\Prompts\MemoryExtractionPrompt;
use App\Services\AI\Prompts\StyleAnalysisPrompt;
use App\Services\AI\Prompts\ReplyGenerationPrompt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAIProvider implements AIProviderInterface
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
            MemoryExtractionPrompt::user($conversation),
            jsonMode: true
        );

        $decoded = json_decode($response, true);

        if (! is_array($decoded)) {
            Log::warning('OpenAI: Failed to parse memory extraction response', ['raw' => $response]);
            return [];
        }

        return $decoded;
    }

    public function analyzeStyle(string $conversation): array
    {
        $response = $this->chat(
            StyleAnalysisPrompt::system(),
            StyleAnalysisPrompt::user($conversation),
            jsonMode: true
        );

        $decoded = json_decode($response, true);

        if (! is_array($decoded)) {
            Log::warning('OpenAI: Failed to parse style analysis response', ['raw' => $response]);
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
        array $memories = [],
        string $guardInstructions = ''
    ): array {
        $response = $this->chat(
            ReplyGenerationPrompt::systemForAutoReply($styleProfile, $memories, $guardInstructions),
            ReplyGenerationPrompt::user($conversation),
            jsonMode: true
        );

        $decoded = json_decode($response, true);

        if (! is_array($decoded) || ! array_key_exists('needs_reply', $decoded)) {
            Log::warning('OpenAI: Failed to parse auto-reply decision response', ['raw' => $response]);
            return ['needs_reply' => false, 'reply' => '', 'withhold_for_owner' => false];
        }

        return [
            'needs_reply'        => (bool) $decoded['needs_reply'],
            'reply'              => (string) ($decoded['reply'] ?? ''),
            'withhold_for_owner' => (bool) ($decoded['withhold_for_owner'] ?? false),
        ];
    }

    public function chat(string $systemPrompt, string $userMessage, bool $jsonMode = false): string
    {
        try {
            $payload = [
                'model'       => $this->model,
                'messages'    => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user',   'content' => $userMessage],
                ],
                'temperature' => 0.7,
            ];

            // Forces the model to only emit valid JSON, rather than just asking nicely in the
            // prompt — mirrors GeminiProvider's responseMimeType handling.
            if ($jsonMode) {
                $payload['response_format'] = ['type' => 'json_object'];
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type'  => 'application/json',
            ])->post($this->endpoint . '/chat/completions', $payload);

            if ($response->failed()) {
                Log::error('OpenAI API Error', ['status' => $response->status(), 'body' => $response->body()]);
                return '';
            }

            return $response->json('choices.0.message.content', '');
        } catch (\Exception $e) {
            Log::error('OpenAI Exception: ' . $e->getMessage());
            return '';
        }
    }
}
