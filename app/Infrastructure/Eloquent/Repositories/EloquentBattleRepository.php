<?php

declare(strict_types=1);

namespace App\Infrastructure\Eloquent\Repositories;

use App\Domain\Battle\ActionType;
use App\Domain\Battle\Battle;
use App\Domain\Battle\BattleState;
use App\Domain\Battle\Map;
use App\Domain\Battle\Repositories\BattleRepositoryInterface;
use App\Domain\Battle\TargetZone;
use App\Domain\Battle\TurnAction;
use App\Domain\Character\Character;
use App\Infrastructure\Eloquent\Models\BattleActionModel;
use App\Infrastructure\Eloquent\Models\BattleModel;
use App\Infrastructure\Eloquent\Models\User as EloquentUser;
use App\Domain\Equipment\Equipment;
use App\Domain\Equipment\EquipmentSlot;
use App\Domain\Weapon\DamageType;
use App\Domain\Weapon\Weapon;
use Illuminate\Support\Facades\DB;

class EloquentBattleRepository implements BattleRepositoryInterface
{
    public function findById(int $id): ?Battle
    {
        $model = BattleModel::with(['participants', 'actions'])->find($id);
        if (!$model) {
            return null;
        }

        return $this->mapToDomain($model);
    }

    public function save(Battle $battle): int
    {
        $model = BattleModel::find($battle->getId());
        if (!$model) {
            $model = new BattleModel();
            if ($battle->getId() > 0) {
                $model->id = $battle->getId();
            }
        }

        $model->state = $battle->getState()->value;
        $model->location_id = $battle->getLocationId();
        $model->round_number = $battle->getRoundNumber();
        $model->round_started_at = $battle->getRoundStartedAt();
        $model->round_duration_seconds = $battle->getRoundDurationSeconds();
        $model->committed_character_ids = $battle->getCommittedCharacterIds();
        $model->map_width = 10; // Default or from VO if available
        $model->map_height = 10; // Default or from VO if available
        $model->save();

        // Sync participants
        $participantIds = array_keys($battle->getParticipants());
        $model->participants()->sync($participantIds);

        // Sync actions for the current round
        $model->actions()->where('round_number', $battle->getRoundNumber())->delete();
        foreach ($battle->getQueuedActions() as $action) {
            BattleActionModel::create([
                'battle_id' => $model->id,
                'user_id' => $action->getCharacterId(),
                'type' => $action->getType()->value,
                'target_zone' => $action->getTargetZone()?->value,
                'from_x' => $action->getFromX(),
                'from_y' => $action->getFromY(),
                'to_x' => $action->getToX(),
                'to_y' => $action->getToY(),
                'round_number' => $battle->getRoundNumber(),
            ]);
        }

        // Update character positions and HP (only if ACTIVE or FINISHED)
        if ($battle->getState() !== BattleState::WAITING) {
            foreach ($battle->getParticipants() as $participant) {
                EloquentUser::where('id', $participant->getId())->update([
                    'hp' => $participant->getCurrentHp(),
                    'x' => $participant->getX(),
                    'y' => $participant->getY(),
                ]);
            }
        }

        return $model->id;
    }

    public function findActive(): array
    {
        $models = BattleModel::where('state', BattleState::ACTIVE->value)->get();

        return $models->map(fn(BattleModel $m) => $this->mapToDomain($m))->toArray();
    }

    public function findActiveByLocation(int $locationId): array
    {
        $models = BattleModel::where('location_id', $locationId)
            ->where('state', '!=', BattleState::FINISHED->value)
            ->get();

        return $models->map(fn(BattleModel $m) => $this->mapToDomain($m))->toArray();
    }

    public function findWaitingByLocation(int $locationId): array
    {
        $models = BattleModel::where('location_id', $locationId)
            ->where('state', BattleState::WAITING->value)
            ->get();

        return $models->map(fn(BattleModel $m) => $this->mapToDomain($m))->toArray();
    }

    public function findJoinableByLocation(int $locationId): array
    {
        $models = BattleModel::where('location_id', $locationId)
            ->whereIn('state', [BattleState::WAITING->value, BattleState::ACTIVE->value])
            ->get();

        return $models->map(fn(BattleModel $m) => $this->mapToDomain($m))->toArray();
    }

    public function isCharacterInBattle(int $characterId): bool
    {
        return DB::table('battle_participants')
            ->join('battles', 'battle_participants.battle_id', '=', 'battles.id')
            ->where('battle_participants.user_id', $characterId)
            ->where('battles.state', '!=', BattleState::FINISHED->value)
            ->exists();
    }

    private function mapToDomain(BattleModel $model): Battle
    {
        $participants = $model->participants->map(function (EloquentUser $user) {
            return $this->mapUserToCharacter($user);
        })->toArray();

        // Key by ID
        $participantsById = [];
        foreach ($participants as $p) {
            $participantsById[$p->getId()] = $p;
        }

        $actions = $model->actions()
            ->where('round_number', $model->round_number)
            ->get()
            ->map(function (BattleActionModel $actionModel) {
                return new TurnAction(
                    $actionModel->user_id,
                    ActionType::from($actionModel->type),
                    $actionModel->target_zone ? TargetZone::from($actionModel->target_zone) : null,
                    $actionModel->from_x,
                    $actionModel->from_y,
                    $actionModel->to_x,
                    $actionModel->to_y
                );
            })->toArray();

        return new Battle(
            $model->id,
            $model->location_id,
            $participantsById,
            new Map($model->map_width, $model->map_height),
            $model->round_number,
            $model->round_started_at,
            $model->round_duration_seconds,
            BattleState::from($model->state),
            $actions,
            $model->committed_character_ids
        );
    }

    private function mapUserToCharacter(EloquentUser $model): Character
    {
        // Copy-pasted/shared logic from EloquentCharacterRepository for now
        $weaponType = $model->weapon;
        if ($weaponType === 'sword') {
            $weapon = new Weapon(1, 'Sword', 8, 14, DamageType::SLASHING, 0.0, 20, 0.50, 90);
        } elseif ($weaponType === 'axe') {
            $weapon = new Weapon(2, 'Axe', 8, 14, DamageType::CHOPPING, 0.0, 60, 0.65);
        } else {
            $weapon = new Weapon(0, 'Fists', 5, 10, DamageType::BLUNT, 0.0, 0, 0.10);
        }

        $equipment = new Equipment();
        $equipment->setItem(EquipmentSlot::MAIN_HAND, $weapon);

        return new Character(
            id: $model->id,
            userId: $model->id, // Assuming user ID is the same as character ID for legacy compatibility
            name: $model->name,
            strength: (int) $model->strength,
            agility: (int) $model->dexterity,
            constitution: (int) $model->constitution ?? 10,
            wit: (int) $model->wit ?? 10,
            maxHp: (int) $model->max_hp,
            currentHp: (int) $model->hp,
            equipment: $equipment,
            locationId: 1, // Defaulting to Training Grounds for now
            x: (int) $model->x,
            y: (int) $model->y,
            blockResistRating: 0, // populated from shield once that system exists
        );
    }
}
