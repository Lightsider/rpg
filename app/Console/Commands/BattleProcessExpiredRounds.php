<?php

namespace App\Console\Commands;

use App\Application\Battle\RoundExpirationHandler;
use Illuminate\Console\Command;

class BattleProcessExpiredRounds extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'battle:process-expired';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Checks all active battles and resolves those where the round time has run out.';

    /**
     * Execute the console command.
     */
    public function handle(RoundExpirationHandler $handler): void
    {
        $handler->handleExpiredRounds();

        $this->info('Processed expired battle rounds.');
    }
}
