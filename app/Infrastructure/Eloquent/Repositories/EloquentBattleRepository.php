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
use App\Application\Battle\NpcFactory;
use App\Domain\Npc\Repositories\NpcTemplateRepositoryInterface;
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
        private readonly ArmorHydrator $armorHydrator,
        private readonly NpcFactory $npcFactory,
        private readonly NpcTemplateRepositoryInterface $npcTemplateRepository
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
        $model->rewards = array_map(fn($r) => $r->jsonSerialize(), $battle->getRewards());
        $model->map_width = $battle->getMap()->getWidth();
        $model->map_height = $battle->getMap()->getHeight();
        $model->fill_with_bots = $battle->shouldFillWithBots();
        $model->save();

        // Sync participants with team info
        // Skip sync for finished battles to preserve archive data
        $participants = $battle->getParticipants();
        $teams = $battle->getParticipantTeams();
        
        if ($battle->getState() !== BattleState::FINISHED) {
            $humanSyncData = [];
            $npcIds = [];
            
            foreach ($participants as $id => $p) {
                if ($id > 0) {
                    $humanSyncData[$id] = ['team' => $teams[$id] ?? null];
                } else {
                    $npcIds[] = -$id; // Convert back to pivot ID

                    $equipmentData = [];
                    foreach ($p->getEquipment()->getAllEquipped() as $slot => $item) {
                        $equipmentData[$slot] = $item->getId();
                    }

                    DB::table('battle_participants')
                        ->where('id', -$id)
                        ->update([
                            'team' => $teams[$id] ?? null,
                            'hp' => $p->getCurrentHp(),
                            'damage_accumulator' => $p->getDamageAccumulator(),
                            'ad_armor_head' => $p->getAdArmorForZone('head'),
                            'ad_armor_chest' => $p->getAdArmorForZone('chest'),
                            'ad_armor_legs' => $p->getAdArmorForZone('legs'),
                            'ad_armor_left_arm' => $p->getAdArmorForZone('left_arm'),
                            'ad_armor_right_arm' => $p->getAdArmorForZone('right_arm'),
                            'strength' => $p->getStrength(),
                            'agility' => $p->getAgility(),
                            'constitution' => $p->getConstitution(),
                            'wit' => $p->getWit(),
                            'name' => $p->getName(),
                            'equipment' => json_encode($equipmentData),
                            'updated_at' => now(),
                        ]);
                }
            }
            
            // Sync humans
            $humanIds = array_keys($humanSyncData);
            DB::table('battle_participants')
                ->where('battle_id', $model->id)
                ->where('is_npc', false)
                ->whereNotIn('character_id', $humanIds)
                ->delete();

            foreach ($humanSyncData as $id => $data) {
                DB::table('battle_participants')->updateOrInsert(
                    ['battle_id' => $model->id, 'character_id' => $id, 'is_npc' => false],
                    array_merge($data, ['updated_at' => now()])
                );
            }
            
            // Remove NPCs NOT in $npcIds
            DB::table('battle_participants')
                ->where('battle_id', $model->id)
                ->where('is_npc', true)
                ->whereNotIn('id', $npcIds)
                ->delete();
        }

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
                if ($participant instanceof Character) {
                    CharacterModel::where('id', $participant->getId())->update([
                        'hp' => $participant->getCurrentHp(),
                        'damage_accumulator' => $participant->getDamageAccumulator(),
                        'ad_armor_head' => $participant->getAdArmorForZone('head'),
                        'ad_armor_chest' => $participant->getAdArmorForZone('chest'),
                        'ad_armor_legs' => $participant->getAdArmorForZone('legs'),
                        'ad_armor_left_arm' => $participant->getAdArmorForZone('left_arm'),
                        'ad_armor_right_arm' => $participant->getAdArmorForZone('right_arm'),
                        'level' => $participant->getLevel(),
                        'experience' => $participant->getExperience(),
                        'currency_copper' => $participant->getCurrencyCopper(),
                    ]);
                } elseif ($participant instanceof \App\Domain\Npc\NpcCombatant) {
                    DB::table('battle_participants')
                        ->where('battle_id', $battle->getId())
                        ->where('id', $participant->getId())
                        ->update([
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

    public function findWaiting(): array
    {
        $models = BattleModel::where('state', BattleState::WAITING->value)->get();

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

    public function lockForUpdate(int $battleId): bool
    {
        return BattleModel::where('id', $battleId)->lockForUpdate()->exists();
    }

    public function delete(int $battleId): void
    {
        BattleModel::where('id', $battleId)->delete();
    }

    public function removeParticipant(int $battleId, int $characterId): void
    {
        $model = BattleModel::find($battleId);
        if (!$model) {
            return;
        }

        $model->participants()->detach($characterId);
    }

    public function hasParticipant(int $battleId, int $characterId): bool
    {
        return DB::table('battle_participants')
            ->where('battle_id', $battleId)
            ->where('character_id', $characterId)
            ->exists();
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

        // Fetch all participants (players and NPCs) directly from pivot table to ensure we get both
        $participantRecords = DB::table('battle_participants')
            ->where('battle_id', $model->id)
            ->get();

        $participantsById = [];
        $participantTeams = [];

        // Preload player characters to avoid N+1 if there are many
        $characterIds = $participantRecords->where('is_npc', false)->pluck('character_id')->filter()->toArray();
        $characters = count($characterIds) > 0 
            ? CharacterModel::with(['weaponItem', 'offHand', 'seal1', 'seal2', 'seal3', 'seal4', 'helmet', 'chest', 'legs', 'gloves'])
                ->whereIn('id', $characterIds)
                ->get()
                ->keyBy('id')
            : collect();

        foreach ($participantRecords as $record) {
            if ($record->is_npc) {
                // Use negative ID for NPCs to avoid clashing with Character IDs
                $combatantId = -(int)$record->id;
                $participantTeams[$combatantId] = $record->team;

                // Hydrate NPC
                $template = $this->npcTemplateRepository->findById((int) $record->npc_template_id);
                if ($template) {
                    $npc = $this->npcFactory->createFromTemplate($template, $combatantId);
                    
                    // Override transient stats if present in pivot table
                    if ($record->hp !== null) {
                        $npc->setCurrentHp((int) $record->hp);
                    }
                    if ($record->damage_accumulator !== null) {
                        $npc->setDamageAccumulator((float) $record->damage_accumulator);
                    }
                    
                    if ($record->name !== null) {
                        $npc->setName($record->name);
                    }

                    if ($record->strength !== null) {
                        $npc->setStats(
                            (int) $record->strength,
                            (int) $record->agility,
                            (int) $record->constitution,
                            (int) $record->wit
                        );
                    }

                    if ($record->equipment !== null) {
                        $equipmentItemIds = json_decode($record->equipment, true);
                        if (is_array($equipmentItemIds)) {
                            // Clear default template equipment first
                            foreach (\App\Domain\Equipment\EquipmentSlot::cases() as $slot) {
                                $npc->getEquipment()->setItem($slot, null);
                            }
                            $this->npcFactory->hydrateEquipment($npc->getEquipment(), $equipmentItemIds);
                        }
                    }

                    $npc->setAdArmorForZone('head', (float) $record->ad_armor_head);
                    $npc->setAdArmorForZone('chest', (float) $record->ad_armor_chest);
                    $npc->setAdArmorForZone('legs', (float) $record->ad_armor_legs);
                    $npc->setAdArmorForZone('left_arm', (float) $record->ad_armor_left_arm);
                    $npc->setAdArmorForZone('right_arm', (float) $record->ad_armor_right_arm);

                    $npc->setPosition(
                        $positions[$combatantId]['x'] ?? 0,
                        $positions[$combatantId]['y'] ?? 0
                    );

                    $participantsById[$combatantId] = $npc;
                }
            } else {
                // Hydrate Player Character
                $characterId = (int) $record->character_id;
                $participantTeams[$characterId] = $record->team;

                if ($characterId && $characters->has($characterId)) {
                    $characterModel = $characters->get($characterId);
                    $character = $this->mapCharacterToDomain($characterModel, $positions);
                    $participantsById[$characterId] = $character;
                }
            }
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
            winnerIds: $model->winner_ids ?? [],
            rewards: array_map(fn($r) => \App\Domain\Battle\BattleReward::fromArray($r), $model->rewards ?? []),
            fillWithBots: (bool) $model->fill_with_bots
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

        $character = new Character(
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
            level: (int) ($characterModel->level ?? 1),
            experience: (int) ($characterModel->experience ?? 0),
        );

        $legacyThresholds = config('game.xp_requirements', []);
        if (is_array($legacyThresholds)) {
            $character->setXpNextLevel($character->getXpForNextLevel($legacyThresholds));
        }

        return $character;
    }

    public function generateNpcCombatantId(int $battleId, int $npcTemplateId, string $team): int
    {
        $pivotId = DB::table('battle_participants')->insertGetId([
            'battle_id' => $battleId,
            'is_npc' => true,
            'npc_template_id' => $npcTemplateId,
            'team' => $team,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return -$pivotId;
    }
}
