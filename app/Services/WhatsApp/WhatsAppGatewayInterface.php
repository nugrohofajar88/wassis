<?php

namespace App\Services\WhatsApp;

interface WhatsAppGatewayInterface
{
    /**
     * Send a text message to a specific number.
     *
     * @param string $to Recipient phone number (e.g. 08123456789)
     * @param string $message Message content
     * @return array Response from the gateway provider
     */
    public function sendMessage(string $to, string $message): array;
}
