<?php

namespace App\Services\WhatsApp;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WablasGateway implements WhatsAppGatewayInterface
{
    public function __construct(
        protected string $token,
        protected string $endpoint
    ) {}

    public function sendMessage(string $to, string $message): array
    {
        $url = rtrim($this->endpoint, '/') . '/api/send-message';

        try {
            $response = Http::withHeaders([
                'Authorization' => $this->token,
            ])->asForm()->post($url, [
                'phone' => $to,
                'message' => $message,
            ]);

            $data = $response->json() ?? ['status' => false, 'error' => 'Invalid JSON response'];

            // Defensive, matching FonnteGateway: flatten `id` to a scalar if the API ever
            // returns it as an array, so callers can store it directly in a DB column.
            if (isset($data['id']) && is_array($data['id'])) {
                $data['id'] = $data['id'][0] ?? null;
            }

            return $data;
        } catch (\Exception $e) {
            Log::error('Wablas API Error: ' . $e->getMessage());
            return ['status' => false, 'error' => $e->getMessage()];
        }
    }
}
