<?php

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            \App\Domain\User\Repositories\UserRepositoryInterface::class,
            \App\Infrastructure\Eloquent\Repositories\EloquentUserRepository::class
        );

        $this->app->bind(
            \App\Domain\Character\Repositories\CharacterRepositoryInterface::class,
            \App\Infrastructure\Eloquent\Repositories\EloquentCharacterRepository::class
        );

        $this->app->bind(
            \App\Domain\Battle\RoundResolverInterface::class,
            \App\Domain\Battle\RoundResolver::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);
    }
}
