<?php

namespace AgenticMorf\LaravelAICognee;

use Illuminate\Support\ServiceProvider;
use Laravel\Ai\Contracts\ConversationStore;
use Laravel\Ai\Storage\DatabaseConversationStore;
use AgenticMorf\LaravelAICognee\Services\CogneeClient;
use AgenticMorf\LaravelAICognee\Services\CogneeSearchService;
use AgenticMorf\LaravelAICognee\Services\ConversationDatasetResolver;
use AgenticMorf\LaravelAICognee\Storage\DatabaseConversationStoreWithCogneePipe;

class LaravelAICogneeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/laravel-ai-cognee.php', 'laravel-ai-cognee');

        $this->app->singleton(CogneeClient::class, function () {
            return new CogneeClient(
                rtrim(config('laravel-ai-cognee.url', 'http://cognee:8000'), '/'),
                config('laravel-ai-cognee.api_token'),
                config('laravel-ai-cognee.timeout', 60)
            );
        });

        $this->app->singleton(CogneeSearchService::class);
        $this->app->singleton(ConversationDatasetResolver::class);

        $this->app->singleton(ConversationStore::class, function ($app) {
            return new DatabaseConversationStoreWithCogneePipe(
                $app->make(DatabaseConversationStore::class)
            );
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/laravel-ai-cognee.php' => config_path('laravel-ai-cognee.php'),
            ], 'laravel-ai-cognee-config');

            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        }
    }
}
