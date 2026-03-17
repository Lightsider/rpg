<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Workerman\Worker;
use App\Infrastructure\WebSockets\GameSocketController;
use App\Infrastructure\WebSockets\MessageRouter;

class GameWebSocketServerCommand extends Command
{
    /**
     * The name and signature of the console command.
     * @var string
     */
    protected $signature = 'game:websocket {action=start}';

    /**
     * The console command description.
     * @var string
     */
    protected $description = 'Start the raw Workerman WebSocket server. Example: php artisan game:websocket start';

    public function handle()
    {
        // Provide argv for Workerman internally
        global $argv;
        $argv[0] = 'artisan';
        $argv[1] = $this->argument('action');

        $router = app(MessageRouter::class);
        $controller = new GameSocketController($router);

        $worker = new Worker('websocket://0.0.0.0:8081');

        $worker->count = 1;

        $worker->onConnect = [$controller, 'onConnect'];
        $worker->onMessage = [$controller, 'onMessage'];
        $worker->onClose = [$controller, 'onClose'];
        $worker->onError = [$controller, 'onError'];

        Worker::runAll();
    }
}
