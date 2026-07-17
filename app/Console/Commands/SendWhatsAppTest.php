<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

use App\Services\WhatsApp\WhatsAppGatewayInterface;

#[Signature('whatsapp:test {to} {message}')]
#[Description('Send a test WhatsApp message using the active gateway')]
class SendWhatsAppTest extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(WhatsAppGatewayInterface $gateway)
    {
        $to = $this->argument('to');
        $message = $this->argument('message');

        $this->info("Sending message via driver: " . config('whatsapp.driver'));
        
        $response = $gateway->sendMessage($to, $message);
        
        $this->line("Response: " . json_encode($response, JSON_PRETTY_PRINT));
    }
}
