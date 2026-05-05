<?php

declare(strict_types=1);

namespace App\Infrastructure\Eloquent\Repositories;

use App\Application\Contracts\TransactionInterface;
use App\Domain\Battle\Repositories\FighterPositionRepositoryInterface;
use App\Infrastructure\Eloquent\Models\FighterPositionModel;

class EloquentFighterPositionRepository implements FighterPositionRepositoryInterface
{
    public function __construct(
        private readonly ?TransactionInterface $transaction = null
    ) {
    }

    public function applyResolvedMoves(int $battleId, array $resolvedMoves): void
    {
        $work = function () use ($battleId, $resolvedMoves): void {
            foreach ($resolvedMoves as $characterId => $target) {
                FighterPositionModel::where('fight_id', $battleId)
                    ->where('character_id', $characterId)
                    ->update(['x' => -1, 'y' => -1 - $characterId]);
            }

            foreach ($resolvedMoves as $characterId => $target) {
                FighterPositionModel::where('fight_id', $battleId)
                    ->where('character_id', $characterId)
                    ->update(['x' => $target['x'], 'y' => $target['y']]);
            }
        };

        if ($this->transaction) {
            $this->transaction->run($work);
            return;
        }

        $work();
    }
}

