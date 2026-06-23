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
    | LiveKit Server-side API Host
    |--------------------------------------------------------------------------
    |
    | The HTTP(S) base URL the backend uses for server-side RoomService RPC
    | calls (token minting is local, but presence removal and soundboard data
    | packets hit the LiveKit API directly). Leave unset to derive it from
    | `url`; set it when the server reaches LiveKit at a different address than
    | clients do — e.g. `http://livekit:7880` inside Docker while clients use
    | `ws://localhost:7880`.
    |
    */
    'host' => env('LIVEKIT_HOST'),

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
