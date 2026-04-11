<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Battle\BlockPenetration\BlockPenetrationConfig;
use App\Domain\Battle\BlockPenetration\BlockPenetrationService;
use App\Domain\Battle\MaxDamage\MaxDamageConfig;
use App\Domain\Battle\MaxDamage\MaxDamageService;
use App\Domain\Battle\MovementResolverInterface;
use App\Services\MovementResolver;
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

        $this->app->bind(
            MovementResolverInterface::class,
            MovementResolver::class
        );

        $this->app->bind(
            \App\Domain\Battle\Repositories\BattleRepositoryInterface::class,
            \App\Infrastructure\Eloquent\Repositories\EloquentBattleRepository::class
        );

        $this->app->bind(
            \App\Domain\Battle\Repositories\BattleLogRepositoryInterface::class,
            \App\Infrastructure\Eloquent\Repositories\EloquentBattleLogRepository::class
        );

        $this->app->bind(
            \App\Domain\Location\Repositories\LocationRepositoryInterface::class,
            \App\Infrastructure\Eloquent\Repositories\EloquentLocationRepository::class
        );

        $this->app->bind(
            \App\Domain\Chat\Repositories\ChatRepositoryInterface::class,
            \App\Infrastructure\Eloquent\Repositories\EloquentChatRepository::class
        );

        $this->app->bind(
            \App\Domain\Chat\Repositories\ChatMessageRepositoryInterface::class,
            \App\Infrastructure\Eloquent\Repositories\EloquentChatMessageRepository::class
        );

        $this->app->bind(
            \App\Domain\Store\Repositories\StoreRepositoryInterface::class,
            \App\Infrastructure\Persistence\EloquentStoreRepository::class
        );

        $this->app->bind(
            \App\Domain\Store\Repositories\StoreItemRepositoryInterface::class,
            \App\Infrastructure\Persistence\EloquentStoreItemRepository::class
        );

        $this->app->bind(
            \App\Domain\Item\Repositories\ItemRepositoryInterface::class,
            \App\Infrastructure\Persistence\EloquentItemRepository::class
        );

        $this->app->bind(
            \App\Domain\Item\Repositories\CharacterItemRepositoryInterface::class,
            \App\Infrastructure\Persistence\EloquentCharacterItemRepository::class
        );

        // BlockPenetrationService is a singleton because it carries per-battle
        // state (failure counters) that must survive across the same request.
        $this->app->singleton(BlockPenetrationService::class, function () {
            return new BlockPenetrationService(
                new BlockPenetrationConfig(
                    k: (int) config('combat.block_penetration.k', 120),
                    maxFinalChance: (float) config('combat.block_penetration.max_final_chance', 0.95),
                    prngScale: (float) config('combat.block_penetration.prng_scale', 0.3),
                )
            );
        });

        $this->app->singleton(\App\Application\Battle\BattleService::class, function ($app) {
            return new \App\Application\Battle\BattleService(
                $app->make(\App\Domain\Battle\Repositories\BattleRepositoryInterface::class),
                $app->make(\App\Domain\Character\Repositories\CharacterRepositoryInterface::class),
                $app->make(\App\Application\Battle\QueueAttackAction::class),
                $app->make(\App\Application\Battle\QueueDefenseAction::class),
                $app->make(\App\Application\Battle\QueueMoveAction::class),
                $app->make(\App\Application\Battle\CommitRoundAction::class)
            );
        });

        $this->app->singleton(MaxDamageService::class, function () {
            return new MaxDamageService(
                new MaxDamageConfig(
                    k: (int) config('combat.max_damage.k', 300),
                    maxFinalChance: (float) config('combat.max_damage.max_final_chance', 0.80),
                    prngScale: (float) config('combat.max_damage.prng_scale', 0.25),
                    debug: (bool) config('combat.max_damage.debug', false),
                )
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);
    }
}
