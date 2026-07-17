<?php

return [
    'driver' => env('WHATSAPP_DRIVER', 'fonnte'),

    'drivers' => [
        'fonnte' => [
            'token' => env('FONNTE_TOKEN'),
            'endpoint' => env('FONNTE_ENDPOINT', 'https://api.fonnte.com'),
        ],
        'wablas' => [
            'token' => env('WABLAS_TOKEN'),
            'endpoint' => env('WABLAS_ENDPOINT', 'https://api.wablas.com'),
        ],
    ],

    // Fonnte does not sign webhook requests, so the configured URL itself carries
    // a shared secret path segment as a lightweight verification mechanism.
    'fonnte_webhook_secret' => env('FONNTE_WEBHOOK_SECRET'),

    // Single-tenant MVP: incoming webhook messages are attributed to this app user.
    'owner_email' => env('WHATSAPP_OWNER_EMAIL'),

    // How long to wait after an inbound message before generating an auto-reply, so a burst
    // of separate WhatsApp bubbles from one person gets treated as one combined thought.
    'auto_reply_debounce_seconds' => env('AUTO_REPLY_DEBOUNCE_SECONDS', 12),

    // Safety ceiling, not a loop detector: max auto-replies sent to one contact per calendar
    // day. Deliberately generous so it never interferes with a real, long conversation — it
    // only bounds worst-case cost/spam if the AI's needs_reply judgment keeps misfiring.
    'auto_reply_daily_limit' => env('AUTO_REPLY_DAILY_LIMIT', 25),

    // Re-run style/memory analysis (App\Jobs\AnalyzeContactHistory) automatically once a contact
    // has accumulated this many new messages since the last analysis, so the style profile keeps
    // improving without the owner remembering to tap "Analisa Ulang". Independent of ai_enabled —
    // style learning happens from any conversation (owner's own replies included), not just
    // auto-reply traffic. Set to 0 to disable.
    'auto_reanalyze_message_interval' => env('AUTO_REANALYZE_MESSAGE_INTERVAL', 15),
];
