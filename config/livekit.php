<?php

return [

    /*
    |--------------------------------------------------------------------------
    | LiveKit Server URL
    |--------------------------------------------------------------------------
    |
    | The WebSocket URL of your LiveKit server instance.
    |
    */
    'url' => env('LIVEKIT_URL', 'ws://localhost:7880'),

    /*
    |--------------------------------------------------------------------------
    | LiveKit API Credentials
    |--------------------------------------------------------------------------
    |
    | The API key and secret used to authenticate with the LiveKit server.
    |
    */
    'api_key' => env('LIVEKIT_API_KEY', ''),
    'api_secret' => env('LIVEKIT_API_SECRET', ''),

    /*
    |--------------------------------------------------------------------------
    | Token TTL
    |--------------------------------------------------------------------------
    |
    | The time-to-live for generated access tokens, in seconds.
    | Default: 6 hours (21600 seconds).
    |
    */
    'token_ttl' => env('LIVEKIT_TOKEN_TTL', 21600),

];
