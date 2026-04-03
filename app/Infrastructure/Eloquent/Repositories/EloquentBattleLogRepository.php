<?php

declare(strict_types=1);

namespace App\Infrastructure\Eloquent\Repositories;

use App\Domain\Battle\BattleLogEntry;
use App\Domain\Battle\Repositories\BattleLogRepositoryInterface;
use App\Infrastructure\Eloquent\Models\BattleLogModel;

class EloquentBattleLogRepository implements BattleLogRepositoryInterface
{
    public function saveBatch(int $battleId, array $entries): void
    {
        foreach ($entries as $entry) {
            BattleLogModel::create([
                'battle_id' => $battleId,
                'round_number' => $entry->roundNumber,
                'type' => $entry->type->value,
                'actor_id' => $entry->actorId,
                'target_id' => $entry->targetId,
                'damage' => $entry->damage,
                'zone' => $entry->zone?->value,
                'outcome' => $entry->outcome,
                'is_crit' => $entry->isCrit,
                'is_max' => $entry->isMax,
                'occurred_at' => $entry->timestamp,
            ]);
        }
    }

    public function findByBattleId(int $battleId): array
    {
        $logs = BattleLogModel::where('battle_id', $battleId)
            ->orderBy('round_number')
            ->orderBy('occurred_at')
            ->get();

        $grouped = [];
        foreach ($logs as $log) {
            $round = $log->round_number;
            if (!isset($grouped[$round])) {
                $grouped[$round] = [
                    'round' => $round,
                    'events' => [],
                ];
            }

            $grouped[$round]['events'][] = [
                'type' => $log->type,
                'actor_id' => $log->actor_id,
                'target_id' => $log->target_id,
                'damage' => $log->damage,
                'zone' => $log->zone,
                'outcome' => $log->outcome,
                'is_crit' => $log->is_crit,
                'is_max' => $log->is_max,
                'occurred_at' => $log->occurred_at->toIso8601String(),
            ];
        }

        return array_values($grouped);
    }
}
