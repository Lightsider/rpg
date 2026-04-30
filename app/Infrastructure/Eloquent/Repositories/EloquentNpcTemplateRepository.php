<?php

declare(strict_types=1);

namespace App\Infrastructure\Eloquent\Repositories;

use App\Domain\Npc\NpcTemplate;
use App\Domain\Npc\NpcType;
use App\Domain\Npc\Repositories\NpcTemplateRepositoryInterface;
use App\Infrastructure\Eloquent\Models\NpcTemplateModel;

class EloquentNpcTemplateRepository implements NpcTemplateRepositoryInterface
{
    public function findById(int $id): ?NpcTemplate
    {
        $model = NpcTemplateModel::find($id);
        if (!$model) {
            return null;
        }

        return $this->mapToDomain($model);
    }

    public function findAll(): array
    {
        $models = NpcTemplateModel::all();
        
        return $models->map(fn (NpcTemplateModel $m) => $this->mapToDomain($m))->toArray();
    }

    private function mapToDomain(NpcTemplateModel $model): NpcTemplate
    {
        return new NpcTemplate(
            id: (int) $model->id,
            name: $model->name,
            type: NpcType::from($model->type),
            strength: $model->strength,
            agility: $model->agility,
            constitution: $model->constitution,
            wit: $model->wit,
            level: $model->level,
            behaviorModel: $model->behavior_model,
            equipmentItemIds: is_array($model->equipment_config) ? $model->equipment_config : null
        );
    }
}
