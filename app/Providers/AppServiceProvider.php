<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Battle\BlockPenetration\BlockPenetrationConfig;
use App\Domain\Battle\BlockPenetration\BlockPenetrationService;
use App\Domain\Battle\MaxDamage\MaxDamageConfig;
use App\Domain\Battle\MaxDamage\MaxDamageService;
use App\Domain\Battle\MovementResolverInterface;
use App\Domain\Battle\Rewards\BattleRewardsConfig;
use App\Application\Contracts\EventDispatcherInterface;
use App\Application\Contracts\PasswordHasherInterface;
use App\Application\Contracts\TransactionInterface;
use App\Infrastructure\Events\LaravelEventDispatcher;
use App\Infrastructure\Persistence\LaravelTransactionManager;
use App\Infrastructure\Security\LaravelPasswordHasher;
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
            \App\Application\Contracts\UserRegistrationNotifierInterface::class,
            \App\Infrastructure\Auth\LaravelUserRegistrationNotifier::class
        );

        $this->app->bind(
            \App\Domain\Character\Repositories\LevelSublevelRepositoryInterface::class,
            \App\Infrastructure\Eloquent\Repositories\LevelSublevelRepository::class
        );

        $this->app->bind(
            \App\Domain\Character\Repositories\ProgressionThresholdsProviderInterface::class,
            \App\Infrastructure\Persistence\ConfigAwareProgressionThresholdsProvider::class
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
            TransactionInterface::class,
            LaravelTransactionManager::class
        );

        $this->app->bind(
            \App\Application\Contracts\ClockInterface::class,
            \App\Infrastructure\Time\SystemClock::class
        );

        $this->app->bind(
            EventDispatcherInterface::class,
            LaravelEventDispatcher::class
        );

        $this->app->bind(
            PasswordHasherInterface::class,
            LaravelPasswordHasher::class
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
            \App\Domain\Battle\Repositories\BattleViewReadRepositoryInterface::class,
            \App\Infrastructure\Eloquent\Repositories\EloquentBattleViewReadRepository::class
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

        $this->app->bind(
            \App\Domain\Npc\Repositories\NpcTemplateRepositoryInterface::class,
            \App\Infrastructure\Eloquent\Repositories\EloquentNpcTemplateRepository::class
        );

        $this->app->singleton(\App\Domain\Npc\Behavior\BehaviorModelRegistry::class, function ($app) {
            $registry = new \App\Domain\Npc\Behavior\BehaviorModelRegistry();
            $registry->register('reach_and_hit', new \App\Domain\Npc\Behavior\ReachAndHitBehavior());
            return $registry;
        });

        $this->app->singleton(\App\Domain\Equipment\EquipmentService::class, function ($app) {
            $slots = $app->make('config')->get('equipment.slots', []);
            return new \App\Domain\Equipment\EquipmentService(is_array($slots) ? $slots : []);
        });

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

        $this->app->singleton(BattleRewardsConfig::class, function () {
            return new BattleRewardsConfig(
                armorKoefs: config('game.armor_koef', [
                    'tank' => 1.0,
                    'universal' => 1.16,
                    'dodge' => 1.38,
                    'non_armor' => 1.0,
                ]),
                levelKoef: (float) config('game.level_koef', 1.5),
                coinBasePerItem: (int) config('game.rewards.coins.base_per_item', 10),
                coinMultipliers: config('game.rewards.coins.multipliers', []),
                teamCoinSplits: config('game.rewards.coins.team_split', [])
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
