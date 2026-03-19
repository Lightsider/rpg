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
use App\Domain\Equipment\Equipment;
use App\Domain\Equipment\EquipmentSlot;
use App\Infrastructure\Eloquent\Models\BattleActionModel;
use App\Infrastructure\Eloquent\Models\BattleModel;
use App\Infrastructure\Eloquent\Models\CharacterModel;
use App\Infrastructure\Eloquent\Models\FighterPositionModel;
use App\Infrastructure\Eloquent\Models\ItemModel;
use App\Infrastructure\Eloquent\Models\User as EloquentUser;
use App\Infrastructure\Eloquent\WeaponHydrator;
use Illuminate\Support\Facades\DB;

class EloquentBattleRepository implements BattleRepositoryInterface
{
    public function __construct(
        private readonly WeaponHydrator $weaponHydrator
    ) {
    }

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
        $model->map_width = $battle->getMap()->getWidth();
        $model->map_height = $battle->getMap()->getHeight();
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
                'blocks' => $action->getBlocks(),
            ]);
        }

        // Update character positions and HP (only if ACTIVE or FINISHED)
        if ($battle->getState() !== BattleState::WAITING) {
            foreach ($battle->getParticipants() as $participant) {
                CharacterModel::where('user_id', $participant->getId())->update([
                    'hp' => $participant->getCurrentHp(),
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

    public function findActiveBattleForCharacter(int $characterId): ?Battle
    {
        $battleId = DB::table('battle_participants')
            ->join('battles', 'battle_participants.battle_id', '=', 'battles.id')
            ->where('battle_participants.user_id', $characterId)
            ->where('battles.state', '!=', BattleState::FINISHED->value)
            ->orderBy('battles.id')
            ->value('battles.id');

        if (!$battleId) {
            return null;
        }

        return $this->findById((int) $battleId);
    }

    private function mapToDomain(BattleModel $model): Battle
    {
        $positions = FighterPositionModel::where('fight_id', $model->id)
            ->get()
            ->mapWithKeys(fn(FighterPositionModel $pos) => [
                $pos->user_id => ['x' => $pos->x, 'y' => $pos->y],
            ])->toArray();

        $participants = $model->participants->map(function (EloquentUser $user) use ($positions) {
            return $this->mapUserToCharacter($user, $positions);
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
                    $actionModel->to_y,
                    $actionModel->blocks ?? []
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

    /**
     * @param array<int, array{x:int, y:int}> $positions
     */
    private function mapUserToCharacter(EloquentUser $model, array $positions): Character
    {
        // Copy-pasted/shared logic from EloquentCharacterRepository for now
        $characterModel = CharacterModel::where('user_id', $model->id)->first();
        $weapon = null;
        if ($characterModel && $characterModel->weapon_id) {
            $item = ItemModel::find($characterModel->weapon_id);
            if ($item && $item->type === 'weapon') {
                $weapon = $this->weaponHydrator->fromItem($item);
            }
        }

        if (!$weapon) {
            $weapon = $this->weaponHydrator->fromLegacyName($characterModel?->weapon);
        }

        $equipment = new Equipment();
        $equipment->setItem(EquipmentSlot::MAIN_HAND, $weapon);

        return new Character(
            id: $model->id,
            userId: $model->id, // Assuming user ID is the same as character ID for legacy compatibility
            name: $characterModel?->name ?? $model->name,
            strength: (int) ($characterModel?->strength ?? 10),
            agility: (int) ($characterModel?->dexterity ?? 10),
            constitution: (int) ($characterModel?->constitution ?? 10),
            wit: (int) ($characterModel?->wit ?? 10),
            maxHp: (int) ($characterModel?->max_hp ?? $model->max_hp),
            currentHp: (int) ($characterModel?->hp ?? $model->hp),
            equipment: $equipment,
            locationId: (int) ($characterModel?->location_id ?? 1),
            x: (int) ($positions[$model->id]['x'] ?? 0),
            y: (int) ($positions[$model->id]['y'] ?? 0),
            blockResistRating: 0, // populated from shield once that system exists
        );
    }
}
