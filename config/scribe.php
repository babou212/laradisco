<?php

use App\Http\Middleware\DocsAccess;
use Knuckles\Scribe\Config\AuthIn;
use Knuckles\Scribe\Config\Defaults;
use Knuckles\Scribe\Extracting\Strategies;

use function Knuckles\Scribe\Config\configureStrategy;

if (! class_exists(AuthIn::class)) {
    return [];
}

return [
    'title' => config('app.name').' API Documentation',

    'description' => 'Internal reference for the Laradisco HTTP API. The API follows the JSON:API standard (https://jsonapi.org) and is built on Laravel\'s native JSON:API resources.',

    'intro_text' => <<<'INTRO'
            Every endpoint lives under the `/api/v1` prefix and speaks [JSON:API](https://jsonapi.org).
            Before using the reference below, read these cross-cutting conventions — the full guide is in the repo at `docs/api/`.

            **Authentication.** The API uses Laravel Sanctum bearer tokens. Obtain one from `POST /api/v1/auth/login`
            (or `/auth/register`) and send it as `Authorization: Bearer <token>` on every authenticated request.

            **Content negotiation.** You must send `Accept: application/json` (or `application/vnd.api+json`).
            Requests that accept neither receive `406 Not Acceptable`.

            **Response envelope.** Single resources are returned as `{ "data": { "type", "id", "attributes", "relationships" } }`
            and collections as `{ "data": [ ... ], "meta": { ... } }`. Related resources appear in a top-level `included`
            array only when requested via `?include=` (comma-separated, e.g. `?include=user,reactions`).

            **Query features** (powered by Spatie Query Builder): `include`, `filter[field]`, and `sort` (comma-separated,
            prefix `-` for descending). Each list endpoint documents its own allowed includes / filters / sorts.

            **Pagination.** Most list endpoints use page-based pagination (`?page=N`) with `meta`/`links`. Message and
            direct-message timelines instead use a custom anchored window (`?before=`, `?after=`, `?around=` message id,
            plus `?limit=`) and return `meta.has_more_before`, `meta.has_more_after`, `meta.oldest_id`, `meta.newest_id`.

            **Idempotency.** For write endpoints that create resources (sending messages, creating DMs/threads, invite
            links), send a unique `Idempotency-Key` header to make retries safe.

            **Rate limiting.** Authenticated traffic is limited to 200 requests/minute per user; auth and other sensitive
            actions are throttled more tightly. Exceeding a limit returns `429 Too Many Requests`.

            **Errors.** Validation failures (`422`) return a JSON:API `errors` array, each with `status`, `title`,
            `detail`, and a `source.pointer` (e.g. `/data/attributes/content`). Authentication (`401`), authorization
            (`403`), and not-found (`404`) responses currently return a simpler `{ "message": "..." }` body.
            INTRO,

    'base_url' => config('app.url'),

    'routes' => [
        [
            'match' => [
                'prefixes' => ['api/v1/*'],

                'domains' => ['*'],
            ],

            'include' => [
                // 'users.index', 'POST /new', '/auth/*'
            ],

            'exclude' => [
                'api.livekit.webhook',
                'POST api/v1/broadcasting/auth',
            ],
        ],
    ],

    'type' => 'laravel',

    'theme' => 'default',

    'static' => [
        'output_path' => 'public/docs',
    ],

    'laravel' => [
        'add_routes' => true,

        'docs_url' => '/docs',

        'assets_directory' => null,

        'middleware' => [
            DocsAccess::class,
        ],
    ],

    'docs_enabled' => env('DOCS_ENABLED', false),

    'external' => [
        'html_attributes' => [],
    ],

    'try_it_out' => [
        'enabled' => true,

        'base_url' => null,

        'use_csrf' => false,

        'csrf_url' => '/sanctum/csrf-cookie',
    ],

    'auth' => [
        'enabled' => true,

        'default' => true,

        'in' => AuthIn::BEARER->value,

        'name' => 'Authorization',

        'use_value' => env('SCRIBE_AUTH_KEY'),

        'placeholder' => '{YOUR_AUTH_TOKEN}',

        'extra_info' => 'Obtain a token from <code>POST /api/v1/auth/login</code> (or <code>/auth/register</code>) and send it as <code>Authorization: Bearer &lt;token&gt;</code>.',
    ],

    'example_languages' => [
        'bash',
        'javascript',
    ],

    'postman' => [
        'enabled' => true,

        'overrides' => [
            // 'info.version' => '2.0.0',
        ],
    ],

    'openapi' => [
        'enabled' => true,

        'version' => '3.0.3',

        'overrides' => [
            // 'info.version' => '2.0.0',
        ],

        'generators' => [],
    ],

    'groups' => [
        'default' => 'Service',

        'order' => [
            'Service',
            'Authentication',
            'Channels & Messages',
            'Threads',
            'Reactions',
            'Pins',
            'Typing',
            'Voice',
            'Soundboard',
            'Direct Messages',
            'Presence',
            'Notifications',
            'Inbox',
            'Mentions',
            'Attachments',
            'Users',
        ],
    ],

    'logo' => false,

    'last_updated' => 'Last updated: {date:F j, Y}',

    'examples' => [
        'faker_seed' => 1234,

        'models_source' => ['factoryMake'],
    ],

    'strategies' => [
        'metadata' => [
            ...Defaults::METADATA_STRATEGIES,
        ],
        'headers' => [
            ...Defaults::HEADERS_STRATEGIES,
            Strategies\StaticData::withSettings(data: [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ]),
        ],
        'urlParameters' => [
            ...Defaults::URL_PARAMETERS_STRATEGIES,
        ],
        'queryParameters' => [
            ...Defaults::QUERY_PARAMETERS_STRATEGIES,
        ],
        'bodyParameters' => [
            ...Defaults::BODY_PARAMETERS_STRATEGIES,
        ],
        'responses' => configureStrategy(
            Defaults::RESPONSES_STRATEGIES,

            Strategies\Responses\ResponseCalls::withSettings(
                only: [],
                config: [
                    'app.debug' => false,
                ]
            )
        ),
        'responseFields' => [
            ...Defaults::RESPONSE_FIELDS_STRATEGIES,
        ],
    ],

    'database_connections_to_transact' => [],

    'fractal' => [
        'serializer' => null,
    ],
];
