<?php

use App\Http\Middleware\DocsAccess;
use Knuckles\Scribe\Config\AuthIn;
use Knuckles\Scribe\Config\Defaults;
use Knuckles\Scribe\Extracting\Strategies;

use function Knuckles\Scribe\Config\configureStrategy;
use function Knuckles\Scribe\Config\removeStrategies;

// Only the most common configs are shown. See the https://scribe.knuckles.wtf/laravel/reference/config for all.

return [
    // The HTML <title> for the generated documentation.
    'title' => config('app.name').' API Documentation',

    // A short description of your API. Will be included in the docs webpage, Postman collection and OpenAPI spec.
    'description' => 'Internal reference for the Laradisco HTTP API. The API follows the JSON:API standard (https://jsonapi.org) and is built on Laravel\'s native JSON:API resources.',

    // Text to place in the "Introduction" section, right after the `description`. Markdown and HTML are supported.
    // The full conventions guide lives in-repo at docs/api/ and is the source of truth; the essentials are summarised below.
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

            **Rate limiting.** Authenticated traffic is limited to 100 requests/minute per user; auth and other sensitive
            actions are throttled more tightly. Exceeding a limit returns `429 Too Many Requests`.

            **Errors.** Validation failures (`422`) return a JSON:API `errors` array, each with `status`, `title`,
            `detail`, and a `source.pointer` (e.g. `/data/attributes/content`). Authentication (`401`), authorization
            (`403`), and not-found (`404`) responses currently return a simpler `{ "message": "..." }` body.
            INTRO,

    // The base URL displayed in the docs.
    // If you're using `laravel` type, you can set this to a dynamic string, like '{{ config("app.tenant_url") }}' to get a dynamic base URL.
    'base_url' => config('app.url'),

    // Routes to include in the docs
    'routes' => [
        [
            'match' => [
                // Match only routes whose paths match this pattern (use * as a wildcard to match any characters). Example: 'users/*'.
                'prefixes' => ['api/v1/*'],

                // Match only routes whose domains match this pattern (use * as a wildcard to match any characters). Example: 'api.*'.
                'domains' => ['*'],
            ],

            // Include these routes even if they did not match the rules above.
            'include' => [
                // 'users.index', 'POST /new', '/auth/*'
            ],

            // Exclude routes that aren't part of the consumer-facing contract:
            // the LiveKit server-to-server webhook and the Reverb broadcasting auth callback.
            'exclude' => [
                'api.livekit.webhook',
                'POST api/v1/broadcasting/auth',
            ],
        ],
    ],

    // The type of documentation output to generate.
    // - "static" will generate a static HTMl page in the /public/docs folder,
    // - "laravel" will generate the documentation as a Blade view, so you can add routing and authentication.
    // - "external_static" and "external_laravel" do the same as above, but pass the OpenAPI spec as a URL to an external UI template
    'type' => 'laravel',

    // See https://scribe.knuckles.wtf/laravel/reference/config#theme for supported options
    'theme' => 'default',

    'static' => [
        // HTML documentation, assets and Postman collection will be generated to this folder.
        // Source Markdown will still be in resources/docs.
        'output_path' => 'public/docs',
    ],

    'laravel' => [
        // Whether to automatically create a docs route for you to view your generated docs. You can still set up routing manually.
        'add_routes' => true,

        // URL path to use for the docs endpoint (if `add_routes` is true).
        // By default, `/docs` opens the HTML page, `/docs.postman` opens the Postman collection, and `/docs.openapi` the OpenAPI spec.
        'docs_url' => '/docs',

        // Directory within `public` in which to store CSS and JS assets.
        // By default, assets are stored in `public/vendor/scribe`.
        // If set, assets will be stored in `public/{{assets_directory}}`
        'assets_directory' => null,

        // Middleware to attach to the docs endpoint (if `add_routes` is true).
        // DocsAccess keeps the reference internal-only: always available outside
        // production, and in production only when DOCS_ENABLED=true.
        'middleware' => [
            DocsAccess::class,
        ],
    ],

    // When true, the generated docs route is reachable even in production.
    // Off by default so the reference is never public; toggle via DOCS_ENABLED.
    'docs_enabled' => env('DOCS_ENABLED', false),

    'external' => [
        'html_attributes' => [],
    ],

    'try_it_out' => [
        // Add a Try It Out button to your endpoints so consumers can test endpoints right from their browser.
        // Don't forget to enable CORS headers for your endpoints.
        'enabled' => true,

        // The base URL to use in the API tester. Leave as null to be the same as the displayed URL (`scribe.base_url`).
        'base_url' => null,

        // [Laravel Sanctum] Fetch a CSRF token before each request, and add it as an X-XSRF-TOKEN header.
        'use_csrf' => false,

        // The URL to fetch the CSRF token from (if `use_csrf` is true).
        'csrf_url' => '/sanctum/csrf-cookie',
    ],

    // How is your API authenticated? This information will be used in the displayed docs, generated examples and response calls.
    'auth' => [
        // Set this to true if ANY endpoints in your API use authentication.
        'enabled' => true,

        // Set this to true if your API should be authenticated by default. If so, you must also set `enabled` (above) to true.
        // You can then use @unauthenticated or @authenticated on individual endpoints to change their status from the default.
        'default' => true,

        // Where is the auth value meant to be sent in a request?
        'in' => AuthIn::BEARER->value,

        // The name of the auth parameter (e.g. token, key, apiKey) or header (e.g. Authorization, Api-Key).
        'name' => 'Authorization',

        // The value of the parameter to be used by Scribe to authenticate response calls.
        // This will NOT be included in the generated documentation. If empty, Scribe will use a random value.
        'use_value' => env('SCRIBE_AUTH_KEY'),

        // Placeholder your users will see for the auth parameter in the example requests.
        // Set this to null if you want Scribe to use a random value as placeholder instead.
        'placeholder' => '{YOUR_AUTH_TOKEN}',

        // Any extra authentication-related info for your users. Markdown and HTML are supported.
        'extra_info' => 'Obtain a token from <code>POST /api/v1/auth/login</code> (or <code>/auth/register</code>) and send it as <code>Authorization: Bearer &lt;token&gt;</code>.',
    ],

    // Example requests for each endpoint will be shown in each of these languages.
    // Supported options are: bash, javascript, php, python
    // To add a language of your own, see https://scribe.knuckles.wtf/laravel/advanced/example-requests
    // Note: does not work for `external` docs types
    'example_languages' => [
        'bash',
        'javascript',
    ],

    // Generate a Postman collection (v2.1.0) in addition to HTML docs.
    // For 'static' docs, the collection will be generated to public/docs/collection.json.
    // For 'laravel' docs, it will be generated to storage/app/scribe/collection.json.
    // Setting `laravel.add_routes` to true (above) will also add a route for the collection.
    'postman' => [
        'enabled' => true,

        'overrides' => [
            // 'info.version' => '2.0.0',
        ],
    ],

    // Generate an OpenAPI spec in addition to docs webpage.
    // For 'static' docs, the collection will be generated to public/docs/openapi.yaml.
    // For 'laravel' docs, it will be generated to storage/app/scribe/openapi.yaml.
    // Setting `laravel.add_routes` to true (above) will also add a route for the spec.
    'openapi' => [
        'enabled' => true,

        // The OpenAPI spec version to generate. Supported versions: '3.0.3', '3.1.0'.
        // OpenAPI 3.1 is more compatible with JSON Schema and is becoming the dominant version.
        // See https://spec.openapis.org/oas/v3.1.0 for details on 3.1 changes.
        'version' => '3.0.3',

        'overrides' => [
            // 'info.version' => '2.0.0',
        ],

        // Additional generators to use when generating the OpenAPI spec.
        // Should extend `Knuckles\Scribe\Writing\OpenApiSpecGenerators\OpenApiGenerator`.
        'generators' => [],
    ],

    'groups' => [
        // Endpoints which don't have a @group will be placed in this default group (e.g. the public /ping healthcheck).
        'default' => 'Service',

        // Sidebar ordering. Listed groups come first in this order; the Settings: * groups follow alphabetically.
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

    // Custom logo path. This will be used as the value of the src attribute for the <img> tag,
    // so make sure it points to an accessible URL or path. Set to false to not use a logo.
    // For example, if your logo is in public/img:
    // - 'logo' => '../img/logo.png' // for `static` type (output folder is public/docs)
    // - 'logo' => 'img/logo.png' // for `laravel` type
    'logo' => false,

    // Customize the "Last updated" value displayed in the docs by specifying tokens and formats.
    // Examples:
    // - {date:F j Y} => March 28, 2022
    // - {git:short} => Short hash of the last Git commit
    // Available tokens are `{date:<format>}` and `{git:<format>}`.
    // The format you pass to `date` will be passed to PHP's `date()` function.
    // The format you pass to `git` can be either "short" or "long".
    // Note: does not work for `external` docs types
    'last_updated' => 'Last updated: {date:F j, Y}',

    'examples' => [
        // Set this to any number to generate the same example values for parameters on each run,
        'faker_seed' => 1234,

        // With API resources and transformers, Scribe tries to generate example models to use in your API responses.
        // Use only `factoryMake` (build in memory, no DB writes) so docs can be generated without a running
        // database — e.g. in CI. Factories live in database/factories/.
        'models_source' => ['factoryMake'],
    ],

    // The strategies Scribe will use to extract information about your routes at each stage.
    // Use configureStrategy() to specify settings for a strategy in the list.
    // Use removeStrategies() to remove an included strategy.
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
            // Disable live response calls: example responses come from @apiResource
            // + model factories and explicit @response blocks, so generation never
            // needs a running database or a real auth token.
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

    // For response calls, API resource responses and transformer responses,
    // Scribe will try to start database transactions, so no changes are persisted to your database.
    // Left empty: model examples use non-persisting `factoryMake` (see examples.models_source),
    // so no transactions are needed and docs can be generated without a database connection.
    'database_connections_to_transact' => [],

    'fractal' => [
        // If you are using a custom serializer with league/fractal, you can specify it here.
        'serializer' => null,
    ],
];
