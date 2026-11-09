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
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        // Fuso dos horários das sessões ao criar os eventos no Google.
        'timezone' => env('GOOGLE_CALENDAR_TIMEZONE', 'America/Sao_Paulo'),
        // Enquanto o app não passa pela verificação do Google, a tela de
        // consentimento mostra um aviso; a página de ajuda explica como seguir.
        'verified' => (bool) env('GOOGLE_APP_VERIFIED', false),
    ],

    'whatsapp' => [
        'token' => env('WHATSAPP_TOKEN'),
        'phone_number_id' => env('WHATSAPP_PHONE_ID'),
        'template_charge' => env('WHATSAPP_TEMPLATE_CHARGE', 'cobranca_iwori'),
        // A Meta desliga cada versão da Graph API cerca de dois anos depois
        // da seguinte: atualize aqui quando ela anunciar o fim da versão em uso.
        'api_version' => env('WHATSAPP_API_VERSION', 'v25.0'),
    ],

];
