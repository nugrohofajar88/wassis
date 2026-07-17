<?php

namespace App\Services\WhatsApp;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FonnteGateway implements WhatsAppGatewayInterface
{
    public function __construct(
        protected string $token,
        protected string $endpoint
    ) {}

    public function sendMessage(string $to, string $message): array
    {
        $url = rtrim($this->endpoint, '/') . '/send';

        try {
            $response = Http::withHeaders([
                'Authorization' => $this->token,
            ])->asForm()->post($url, [
                'target' => $to,
                'message' => $message,
            ]);

            $data = $response->json() ?? ['status' => false, 'error' => 'Invalid JSON response'];

            // Fonnte returns `id` as an array (one entry per target device) even for a single
            // recipient — flatten to a scalar so callers can store it directly in a DB column.
            if (isset($data['id']) && is_array($data['id'])) {
                $data['id'] = $data['id'][0] ?? null;
            }

            return $data;
        } catch (\Exception $e) {
            Log::error('Fonnte API Error: ' . $e->getMessage());
            return ['status' => false, 'error' => $e->getMessage()];
        }
    }
}
