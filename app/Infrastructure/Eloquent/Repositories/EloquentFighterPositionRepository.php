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
            // 1. Snapshot current positions and move everyone to temporary off-map spots.
            // This clears the board so any move (or stay) can be applied without collision.
            $positions = FighterPositionModel::where('fight_id', $battleId)->get()->keyBy('character_id');
            
            // Store original positions before updating to temporary spots
            $originalPositions = [];
            foreach ($positions as $charId => $posModel) {
                $originalPositions[$charId] = [
                    'x' => $posModel->x,
                    'y' => $posModel->y
                ];
            }
            
            foreach ($positions as $charId => $posModel) {
                $posModel->update(['x' => -1, 'y' => -1 - $charId]);
            }

            // 2. Apply either the new resolved move OR restore the original position.
            foreach ($positions as $charId => $posModel) {
                $target = $resolvedMoves[$charId] ?? $originalPositions[$charId];
                
                $posModel->update([
                    'x' => $target['x'],
                    'y' => $target['y']
                ]);
            }
        };

        if ($this->transaction) {
            $this->transaction->run($work);
            return;
        }

        $work();
    }
}

