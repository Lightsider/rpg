<?php

declare(strict_types=1);

namespace App\Application\Battle;

use App\Domain\Battle\BattleState;
use App\Domain\DomainException;
use App\Infrastructure\Eloquent\Models\BattleModel;
use Illuminate\Support\Facades\DB;

class LeaveWaitingBattleAction
{
    public function execute(int $battleId, int $characterId): void
    {
        DB::transaction(function () use ($battleId, $characterId) {
            $battleModel = BattleModel::with('participants')->find($battleId);
            if (!$battleModel) {
                throw new DomainException('Fight not found.');
            }

            if ($battleModel->state !== BattleState::WAITING->value) {
                throw new DomainException('Fight is no longer waiting.');
            }

            if (!$battleModel->participants->contains('id', $characterId)) {
                throw new DomainException('You are not a participant in this fight.');
            }

            $battleModel->participants()->detach($characterId);
            $battleModel->delete();
        });
    }
}
