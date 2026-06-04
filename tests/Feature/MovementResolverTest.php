<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Battle\ActionType;
use App\Domain\Battle\Battle;
use App\Domain\Battle\BattleState;
use App\Domain\Battle\Map;
use App\Domain\Battle\TurnAction;
use App\Domain\Weapon\DamageType;
use App\Domain\Weapon\Weapon;
use App\Infrastructure\Eloquent\Models\BattleModel;
use App\Infrastructure\Eloquent\Models\CharacterModel;
use App\Infrastructure\Eloquent\Models\FighterPositionModel;
use App\Infrastructure\Eloquent\Repositories\EloquentFighterPositionRepository;
use App\Infrastructure\Eloquent\Models\LocationModel;
use App\Infrastructure\Eloquent\Models\User;
use App\Application\Battle\MovementResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MovementResolverTest extends TestCase
{
    use RefreshDatabase;

    private function createCharacter(int $id, int $x, int $y): \App\Domain\Character\Character
    {
        $weapon = new Weapon(1, 'Sword', 10, 20, DamageType::SLASHING, 0.9, 0, 0.0);
        $equipment = new \App\Domain\Equipment\Equipment();
        $equipment->setItem(\App\Domain\Equipment\EquipmentSlot::MAIN_HAND, $weapon);

        return new \App\Domain\Character\Character(
            id: $id,
            userId: $id,
            name: "Char $id",
            strength: 10,
            agility: 10,
            constitution: 10,
            wit: 10,
            maxHp: 100,
            currentHp: 100,
            equipment: $equipment,
            x: $x,
            y: $y
        );
    }

    public function test_resolves_swap_moves(): void
    {
        $location = LocationModel::create([
            'name' => 'Arena',
            'description' => 'Test arena',
        ]);

        $battleModel = BattleModel::create([
            'state' => BattleState::ACTIVE->value,
            'location_id' => $location->id,
            'round_number' => 1,
            'round_started_at' => now(),
            'round_duration_seconds' => 60,
            'committed_character_ids' => [],
            'map_width' => 5,
            'map_height' => 3,
        ]);

        $userOne = User::factory()->create();
        $userTwo = User::factory()->create();

        $charOneModel = CharacterModel::create([
            'user_id' => $userOne->id,
            'name' => 'Char 1',
            'strength' => 10,
            'dexterity' => 10,
            'constitution' => 10,
            'wit' => 10,
            'hp' => 100,
            'max_hp' => 100,
            'location_id' => $location->id,
        ]);

        $charTwoModel = CharacterModel::create([
            'user_id' => $userTwo->id,
            'name' => 'Char 2',
            'strength' => 10,
            'dexterity' => 10,
            'constitution' => 10,
            'wit' => 10,
            'hp' => 100,
            'max_hp' => 100,
            'location_id' => $location->id,
        ]);

        FighterPositionModel::create([
            'fight_id' => $battleModel->id,
            'character_id' => $charOneModel->id,
            'x' => 1,
            'y' => 1,
        ]);

        FighterPositionModel::create([
            'fight_id' => $battleModel->id,
            'character_id' => $charTwoModel->id,
            'x' => 2,
            'y' => 1,
        ]);

        $charOne = $this->createCharacter($charOneModel->id, 1, 1);
        $charTwo = $this->createCharacter($charTwoModel->id, 2, 1);

        $battle = new Battle(
            id: $battleModel->id,
            locationId: $location->id,
            participants: [$charOne->getId() => $charOne, $charTwo->getId() => $charTwo],
            map: new Map(5, 3),
            state: BattleState::ACTIVE,
            queuedActions: [
                new TurnAction(
                    characterId: $charOne->getId(),
                    type: ActionType::MOVE,
                    targetZone: null,
                    targetId: null,
                    fromX: 1,
                    fromY: 1,
                    toX: 2,
                    toY: 1
                ),
                new TurnAction(
                    characterId: $charTwo->getId(),
                    type: ActionType::MOVE,
                    targetZone: null,
                    targetId: null,
                    fromX: 2,
                    fromY: 1,
                    toX: 1,
                    toY: 1
                ),
            ]
        );

        $resolver = new MovementResolver(new EloquentFighterPositionRepository());
        $resolver->resolveMovement($battle);

        $this->assertDatabaseHas('fighter_positions', [
            'fight_id' => $battleModel->id,
            'character_id' => $charOneModel->id,
            'x' => 2,
            'y' => 1,
        ]);

        $this->assertDatabaseHas('fighter_positions', [
            'fight_id' => $battleModel->id,
            'character_id' => $charTwoModel->id,
            'x' => 1,
            'y' => 1,
        ]);
    }

    public function test_lowest_id_wins_conflict(): void
    {
        $location = LocationModel::create([
            'name' => 'Arena',
            'description' => 'Test arena',
        ]);

        $battleModel = BattleModel::create([
            'state' => BattleState::ACTIVE->value,
            'location_id' => $location->id,
            'round_number' => 1,
            'round_started_at' => now(),
            'round_duration_seconds' => 60,
            'committed_character_ids' => [],
            'map_width' => 5,
            'map_height' => 3,
        ]);

        $userOne = User::factory()->create();
        $userTwo = User::factory()->create();

        $charOneModel = CharacterModel::create([
            'user_id' => $userOne->id,
            'name' => 'Char 1',
            'strength' => 10,
            'dexterity' => 10,
            'constitution' => 10,
            'wit' => 10,
            'hp' => 100,
            'max_hp' => 100,
            'location_id' => $location->id,
        ]);

        $charTwoModel = CharacterModel::create([
            'user_id' => $userTwo->id,
            'name' => 'Char 2',
            'strength' => 10,
            'dexterity' => 10,
            'constitution' => 10,
            'wit' => 10,
            'hp' => 100,
            'max_hp' => 100,
            'location_id' => $location->id,
        ]);

        FighterPositionModel::create([
            'fight_id' => $battleModel->id,
            'character_id' => $charOneModel->id,
            'x' => 0,
            'y' => 1,
        ]);

        FighterPositionModel::create([
            'fight_id' => $battleModel->id,
            'character_id' => $charTwoModel->id,
            'x' => 2,
            'y' => 1,
        ]);

        $charOne = $this->createCharacter($charOneModel->id, 0, 1);
        $charTwo = $this->createCharacter($charTwoModel->id, 2, 1);

        $battle = new Battle(
            id: $battleModel->id,
            locationId: $location->id,
            participants: [$charOne->getId() => $charOne, $charTwo->getId() => $charTwo],
            map: new Map(5, 3),
            state: BattleState::ACTIVE,
            queuedActions: [
                new TurnAction(
                    characterId: $charOne->getId(),
                    type: ActionType::MOVE,
                    targetZone: null,
                    targetId: null,
                    fromX: 0,
                    fromY: 1,
                    toX: 1,
                    toY: 1
                ),
                new TurnAction(
                    characterId: $charTwo->getId(),
                    type: ActionType::MOVE,
                    targetZone: null,
                    targetId: null,
                    fromX: 2,
                    fromY: 1,
                    toX: 1,
                    toY: 1
                ),
            ]
        );

        $resolver = new MovementResolver(new EloquentFighterPositionRepository());
        $resolver->resolveMovement($battle);

        $this->assertDatabaseHas('fighter_positions', [
            'fight_id' => $battleModel->id,
            'character_id' => $charOneModel->id,
            'x' => 1,
            'y' => 1,
        ]);

        $this->assertDatabaseHas('fighter_positions', [
            'fight_id' => $battleModel->id,
            'character_id' => $charTwoModel->id,
            'x' => 2,
            'y' => 1,
        ]);
    }
}
