<?php

namespace App\Providers;

use App\Services\LiveKitService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Resources\JsonApi\AnonymousResourceCollection;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(LiveKitService::class, function ($app): LiveKitService {
            return new LiveKitService(
                apiKey: config('livekit.api_key'),
                apiSecret: config('livekit.api_secret'),
                url: config('livekit.url'),
                tokenTtl: config('livekit.token_ttl'),
            );
        });
    }

    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureJsonApiMacros();
    }

    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null
        );
    }

    protected function configureJsonApiMacros(): void
    {
        AnonymousResourceCollection::macro('includePreviouslyLoadedRelationships', function () {
            /** @var AnonymousResourceCollection $this */
            $this->collection->each(function ($resource) {
                if ($resource instanceof JsonApiResource) {
                    $resource->includePreviouslyLoadedRelationships();
                }
            });

            return $this;
        });
    }
}
