<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Character\CurrencyService;
use Illuminate\Console\Command;

class AddCurrencyCommand extends Command
{
    protected $signature = 'rpg:add-currency {characterId} {amount}';
    protected $description = 'Add copper to a character';

    public function handle(CurrencyService $currencyService): int
    {
        $characterId = (int) $this->argument('characterId');
        $amount = (int) $this->argument('amount');

        try {
            $newBalance = $currencyService->addCurrency($characterId, $amount);
            $this->info("Success! New balance for character {$characterId}: {$newBalance} copper.");
            return 0;
        } catch (\Exception $e) {
            $this->error($e->getMessage());
            return 1;
        }
    }
}
