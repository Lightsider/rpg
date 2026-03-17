<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Infrastructure\WebSockets\MessageRouter;
use App\Infrastructure\WebSockets\Handlers\BattleSocketHandler;
use App\Infrastructure\WebSockets\Handlers\MovementSocketHandler;
use App\Infrastructure\WebSockets\Handlers\ChatSocketHandler;
use App\Application\Battle\BattleService;

class WebSocketServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MessageRouter::class, function ($app) {
            $router = new MessageRouter();
            
            // Register all handlers
            $router->registerHandler('joinBattle', $app->make(BattleSocketHandler::class));
            $router->registerHandler('submitTurn', $app->make(BattleSocketHandler::class));
            $router->registerHandler('move', $app->make(MovementSocketHandler::class));
            $router->registerHandler('chat', $app->make(ChatSocketHandler::class));

            return $router;
        });

        // Register BattleService as singleton so it retains connections across requests in same worker process
        $this->app->singleton(BattleService::class, function ($app) {
            return new BattleService(
                $app->make(\App\Domain\Battle\Repositories\BattleRepositoryInterface::class),
                $app->make(\App\Domain\Character\Repositories\CharacterRepositoryInterface::class),
                $app->make(\App\Application\Battle\QueueAttackAction::class),
                $app->make(\App\Application\Battle\QueueDefenseAction::class),
                $app->make(\App\Application\Battle\QueueMoveAction::class),
                $app->make(\App\Application\Battle\CommitRoundAction::class)
            );
        });
    }
}
