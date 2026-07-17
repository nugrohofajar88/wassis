<?php

return [
    'driver' => env('AI_DRIVER', 'openai'),

    'drivers' => [
        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'model'   => env('OPENAI_MODEL', 'gpt-4o-mini'),
            'endpoint' => env('OPENAI_ENDPOINT', 'https://api.openai.com/v1'),
        ],
        'gemini' => [
            'api_key' => env('GEMINI_API_KEY'),
            'model'   => env('GEMINI_MODEL', 'gemini-2.0-flash'),
            'endpoint' => env('GEMINI_ENDPOINT', 'https://generativelanguage.googleapis.com/v1beta'),
        ],
    ],
];
