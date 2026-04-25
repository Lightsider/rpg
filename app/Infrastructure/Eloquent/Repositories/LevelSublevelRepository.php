<?php

namespace App\Infrastructure\Eloquent\Repositories;

use App\Infrastructure\Eloquent\Models\LevelSublevelModel;

class LevelSublevelRepository
{
    /**
     * @return array<int, array{xp_threshold: int, reward_copper: int}>
     */
    public function getThresholdsForLevel(int $level): array
    {
        $models = LevelSublevelModel::where('level', $level)
            ->orderBy('sublevel_index')
            ->get();
            
        $thresholds = [];
        foreach ($models as $model) {
            $thresholds[$model->sublevel_index] = [
                'xp_threshold' => (int) $model->xp_threshold,
                'reward_copper' => (int) $model->reward_copper,
            ];
        }
        
        return $thresholds;
    }
}
