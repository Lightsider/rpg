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
use App\Infrastructure\Eloquent\WeaponHydrator;
use App\Infrastructure\Eloquent\SealHydrator;
use App\Infrastructure\Eloquent\ArmorHydrator;
use Illuminate\Support\Facades\DB;

class EloquentBattleRepository implements BattleRepositoryInterface
{
    public function __construct(
        private readonly WeaponHydrator $weaponHydrator,
        private readonly SealHydrator $sealHydrator,
        private readonly ArmorHydrator $armorHydrator
    ) {
    }

    public function findById(int $id): ?Battle
    {
        $model = BattleModel::with([
            'participants' => function ($query) {
                $query->with([
                    'weaponItem', 'offHand', 'seal1', 'seal2', 'seal3', 'seal4',
                    'helmet', 'chest', 'legs', 'gloves'
                ]);
            },
            'actions',
        ])->find($id);
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
        $model->max_participants = $battle->getMaxParticipants();
        $model->start_timeout_seconds = $battle->getStartTimeoutSeconds();
        $model->committed_character_ids = $battle->getCommittedCharacterIds();
        $model->winner_ids = $battle->getWinnerIds();
        $model->map_width = $battle->getMap()->getWidth();
        $model->map_height = $battle->getMap()->getHeight();
        $model->save();

        // Sync participants with team info
        $participantIds = array_keys($battle->getParticipants());
        $teams = $battle->getParticipantTeams();
        $syncData = [];
        foreach ($participantIds as $id) {
            $syncData[$id] = ['team' => $teams[$id] ?? null];
        }
        $model->participants()->sync($syncData);

        // Sync actions for the current round
        $model->actions()->where('round_number', $battle->getRoundNumber())->delete();
        foreach ($battle->getQueuedActions() as $action) {
            BattleActionModel::create([
                'battle_id' => $model->id,
                'character_id' => $action->getCharacterId(),
                'target_id' => $action->getTargetId(),
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
                CharacterModel::where('id', $participant->getId())->update([
                    'hp' => $participant->getCurrentHp(),
                    'damage_accumulator' => $participant->getDamageAccumulator(),
                    'ad_armor_head' => $participant->getAdArmorForZone('head'),
                    'ad_armor_chest' => $participant->getAdArmorForZone('chest'),
                    'ad_armor_legs' => $participant->getAdArmorForZone('legs'),
                    'ad_armor_left_arm' => $participant->getAdArmorForZone('left_arm'),
                    'ad_armor_right_arm' => $participant->getAdArmorForZone('right_arm'),
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
            ->where('battle_participants.character_id', $characterId)
            ->where('battles.state', '!=', BattleState::FINISHED->value)
            ->exists();
    }

    public function findActiveBattleForCharacter(int $characterId): ?Battle
    {
        $battleId = DB::table('battle_participants')
            ->join('battles', 'battle_participants.battle_id', '=', 'battles.id')
            ->where('battle_participants.character_id', $characterId)
            ->where('battles.state', '!=', BattleState::FINISHED->value)
            ->orderBy('battles.id')
            ->value('battles.id');

        if (!$battleId) {
            return null;
        }

        return $this->findById((int) $battleId);
    }

    public function findRecent(int $limit = 15): array
    {
        $models = BattleModel::with(['participants', 'location'])
            ->orderBy('updated_at', 'desc')
            ->limit($limit)
            ->get();

        return $models->map(fn(BattleModel $m) => $this->mapToDomain($m))->toArray();
    }

    private function mapToDomain(BattleModel $model): Battle
    {
        $positions = FighterPositionModel::where('fight_id', $model->id)
            ->get()
            ->mapWithKeys(fn(FighterPositionModel $pos) => [
                $pos->character_id => ['x' => $pos->x, 'y' => $pos->y],
            ])->toArray();

        $participants = $model->participants->map(function (CharacterModel $character) use ($positions) {
            return $this->mapCharacterToDomain($character, $positions);
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
                    characterId: $actionModel->character_id,
                    type: ActionType::from($actionModel->type),
                    targetZone: $actionModel->target_zone ? TargetZone::from($actionModel->target_zone) : null,
                    targetId: $actionModel->target_id,
                    fromX: $actionModel->from_x,
                    fromY: $actionModel->from_y,
                    toX: $actionModel->to_x,
                    toY: $actionModel->to_y,
                    blocks: $actionModel->blocks ?? []
                );
            })->toArray();

        $participantTeams = [];
        foreach ($model->participants as $participantModel) {
            if ($participantModel->pivot?->team) {
                $participantTeams[$participantModel->id] = $participantModel->pivot->team;
            }
        }

        return new Battle(
            id: $model->id,
            locationId: $model->location_id,
            participants: $participantsById,
            map: new Map($model->map_width, $model->map_height),
            roundNumber: $model->round_number,
            roundStartedAt: $model->round_started_at ?? new \DateTimeImmutable(),
            roundDurationSeconds: $model->round_duration_seconds,
            state: BattleState::from($model->state),
            queuedActions: $actions,
            committedCharacterIds: $model->committed_character_ids,
            maxParticipants: $model->max_participants !== null ? (int) $model->max_participants : null,
            startTimeoutSeconds: $model->start_timeout_seconds !== null ? (int) $model->start_timeout_seconds : null,
            participantTeams: $participantTeams,
            winnerIds: $model->winner_ids ?? []
        );
    }

    /**
     * @param array<int, array{x:int, y:int}> $positions
     */
    private function mapCharacterToDomain(CharacterModel $characterModel, array $positions): Character
    {
        $weapon = null;
        if ($characterModel->weapon_id) {
            $item = ItemModel::find($characterModel->weapon_id);
            if ($item && $item->type === 'weapon') {
                $weapon = $this->weaponHydrator->fromItem($item);
            }
        }

        if (!$weapon && $characterModel?->weapon) {
            $weapon = $this->weaponHydrator->fromLegacyName($characterModel->weapon);
        }

        $equipment = new Equipment();
        if ($weapon) {
            $equipment->setItem(EquipmentSlot::MAIN_HAND, $weapon);
        }

        if ($characterModel?->offHand) {
            if (in_array($characterModel->offHand->type, ['shield', 'armor'], true)) {
                $offhand = $this->armorHydrator->fromItem($characterModel->offHand);
                $equipment->setItem(EquipmentSlot::OFF_HAND, $offhand);
            } elseif (in_array($characterModel->offHand->type, ['offhand_weapon', 'weapon'], true)) {
                $offhand = $this->weaponHydrator->fromItem($characterModel->offHand);
                $equipment->setItem(EquipmentSlot::OFF_HAND, $offhand);
            }
        }

        foreach ([1, 2, 3, 4] as $i) {
            $relation = "seal{$i}";
            if ($characterModel && $characterModel->$relation) {
                $seal = $this->sealHydrator->fromItem($characterModel->$relation);
                $slot = Constant("App\Domain\Equipment\EquipmentSlot::SEAL_{$i}");
                $equipment->setItem($slot, $seal);
            }
        }

        $armorRelations = [
            'helmet' => EquipmentSlot::HELMET,
            'chest' => EquipmentSlot::CHEST,
            'legs' => EquipmentSlot::LEGS,
            'gloves' => EquipmentSlot::GLOVES,
        ];

        foreach ($armorRelations as $relation => $slot) {
            if ($characterModel && $characterModel->$relation) {
                $armor = $this->armorHydrator->fromItem($characterModel->$relation);
                $equipment->setItem($slot, $armor);
            }
        }

        $chestArmor = (float) ($characterModel->ad_armor_chest ?? 0.0);
        $handsArmor = (float) ($characterModel->ad_armor_hands ?? 0.0);
        $armFallback = ($chestArmor * 0.5) + ($handsArmor * 0.5);
        $leftArm = $characterModel->ad_armor_left_arm !== null ? (float) $characterModel->ad_armor_left_arm : $armFallback;
        $rightArm = $characterModel->ad_armor_right_arm !== null ? (float) $characterModel->ad_armor_right_arm : $armFallback;

        return new Character(
            id: (int) $characterModel->id,
            userId: (int) $characterModel->user_id,
            name: $characterModel->name,
            strength: (int) ($characterModel->strength),
            agility: (int) ($characterModel->dexterity),
            constitution: (int) ($characterModel->constitution),
            wit: (int) ($characterModel->wit),
            maxHp: (int) ($characterModel->max_hp),
            currentHp: (int) ($characterModel->hp),
            equipment: $equipment,
            damageAccumulator: (float) ($characterModel->damage_accumulator ?? 0.0),
            currencyCopper: (int) ($characterModel->currency_copper ?? 0),
            locationId: (int) ($characterModel->location_id),
            x: (int) ($positions[$characterModel->id]['x'] ?? 0),
            y: (int) ($positions[$characterModel->id]['y'] ?? 0),
            blockResistRating: 0,
            adArmorHead: (float) ($characterModel->ad_armor_head ?? 0.0),
            adArmorChest: $chestArmor,
            adArmorLegs: (float) ($characterModel->ad_armor_legs ?? 0.0),
            adArmorLeftArm: $leftArm,
            adArmorRightArm: $rightArm,
        );
    }
}
