<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    | Sprachmodell für Kuration, Assistent und Medienanalyse. Ohne Schlüssel
    | bleiben diese Funktionen abgeschaltet, die Bibliothek läuft weiter.
    | Modell-IDs siehe https://docs.claude.com/en/docs/about-claude/models
    */
    'anthropic' => [
        // Ausdrücklich auf String festgelegt. `config('…', '')` hilft hier
        // nicht: Der zweite Parameter greift nur, wenn der Schlüssel im Array
        // fehlt – ein vorhandener Eintrag mit dem Wert null kommt als null an
        // und lief in den Diensten gegen eine typisierte Eigenschaft.
        'key'   => (string) env('ANTHROPIC_API_KEY', ''),
        'model' => (string) env('ANTHROPIC_MODEL', 'claude-haiku-4-5-20251001'),
    ],

    /*
    | Embeddings für die Ähnlichkeitssuche. Nur nötig, wenn "Ähnliche Medien"
    | und die semantische Suche genutzt werden sollen.
    */
    'openai' => [
        'key'             => (string) env('OPENAI_API_KEY', ''),
        'embedding_model' => (string) env('OPENAI_EMBEDDING_MODEL', 'text-embedding-3-small'),
    ],

    'google_books' => [
        'key' => (string) env('GOOGLE_BOOKS_API_KEY', ''),
    ],

];
