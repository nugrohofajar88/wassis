<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

use App\Services\WhatsApp\WhatsAppGatewayInterface;
use App\Services\WhatsApp\FonnteGateway;
use App\Services\WhatsApp\WablasGateway;

use App\Services\AI\AIProviderInterface;
use App\Services\AI\OpenAIProvider;
use App\Services\AI\GeminiProvider;
use App\Services\Memory\MemoryEngine;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // ─── WhatsApp Gateway ─────────────────────────────────────────
        $this->app->singleton(WhatsAppGatewayInterface::class, function ($app) {
            $driver = config('whatsapp.driver', 'fonnte');

            if ($driver === 'wablas') {
                return new WablasGateway(
                    config('whatsapp.drivers.wablas.token') ?? '',
                    config('whatsapp.drivers.wablas.endpoint') ?? 'https://api.wablas.com'
                );
            }

            return new FonnteGateway(
                config('whatsapp.drivers.fonnte.token') ?? '',
                config('whatsapp.drivers.fonnte.endpoint') ?? 'https://api.fonnte.com'
            );
        });

        // ─── AI Provider ──────────────────────────────────────────────
        $this->app->singleton(AIProviderInterface::class, function ($app) {
            $driver = config('ai.driver', 'openai');

            if ($driver === 'gemini') {
                return new GeminiProvider(
                    config('ai.drivers.gemini.api_key') ?? '',
                    config('ai.drivers.gemini.model', 'gemini-2.0-flash'),
                    config('ai.drivers.gemini.endpoint', 'https://generativelanguage.googleapis.com/v1beta')
                );
            }

            return new OpenAIProvider(
                config('ai.drivers.openai.api_key') ?? '',
                config('ai.drivers.openai.model', 'gpt-4o-mini'),
                config('ai.drivers.openai.endpoint', 'https://api.openai.com/v1')
            );
        });

        // ─── Memory Engine ────────────────────────────────────────────
        $this->app->singleton(MemoryEngine::class, function ($app) {
            return new MemoryEngine(
                $app->make(AIProviderInterface::class)
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
