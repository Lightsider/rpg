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
use App\Infrastructure\Eloquent\Models\FighterPositionModel;
use App\Infrastructure\Eloquent\Models\LocationModel;
use App\Infrastructure\Eloquent\Models\User;
use App\Services\MovementResolver;
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

        FighterPositionModel::create([
            'fight_id' => $battleModel->id,
            'user_id' => $userOne->id,
            'x' => 1,
            'y' => 1,
        ]);

        FighterPositionModel::create([
            'fight_id' => $battleModel->id,
            'user_id' => $userTwo->id,
            'x' => 2,
            'y' => 1,
        ]);

        $charOne = $this->createCharacter($userOne->id, 1, 1);
        $charTwo = $this->createCharacter($userTwo->id, 2, 1);

        $battle = new Battle(
            id: $battleModel->id,
            locationId: $location->id,
            participants: [$charOne->getId() => $charOne, $charTwo->getId() => $charTwo],
            map: new Map(5, 3),
            state: BattleState::ACTIVE,
            queuedActions: [
                new TurnAction($charOne->getId(), ActionType::MOVE, null, 1, 1, 2, 1),
                new TurnAction($charTwo->getId(), ActionType::MOVE, null, 2, 1, 1, 1),
            ]
        );

        $resolver = new MovementResolver();
        $resolver->resolveMovement($battle);

        $this->assertDatabaseHas('fighter_positions', [
            'fight_id' => $battleModel->id,
            'user_id' => $userOne->id,
            'x' => 2,
            'y' => 1,
        ]);

        $this->assertDatabaseHas('fighter_positions', [
            'fight_id' => $battleModel->id,
            'user_id' => $userTwo->id,
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

        FighterPositionModel::create([
            'fight_id' => $battleModel->id,
            'user_id' => $userOne->id,
            'x' => 0,
            'y' => 1,
        ]);

        FighterPositionModel::create([
            'fight_id' => $battleModel->id,
            'user_id' => $userTwo->id,
            'x' => 2,
            'y' => 1,
        ]);

        $charOne = $this->createCharacter($userOne->id, 0, 1);
        $charTwo = $this->createCharacter($userTwo->id, 2, 1);

        $battle = new Battle(
            id: $battleModel->id,
            locationId: $location->id,
            participants: [$charOne->getId() => $charOne, $charTwo->getId() => $charTwo],
            map: new Map(5, 3),
            state: BattleState::ACTIVE,
            queuedActions: [
                new TurnAction($charOne->getId(), ActionType::MOVE, null, 0, 1, 1, 1),
                new TurnAction($charTwo->getId(), ActionType::MOVE, null, 2, 1, 1, 1),
            ]
        );

        $resolver = new MovementResolver();
        $resolver->resolveMovement($battle);

        $this->assertDatabaseHas('fighter_positions', [
            'fight_id' => $battleModel->id,
            'user_id' => $userOne->id,
            'x' => 1,
            'y' => 1,
        ]);

        $this->assertDatabaseHas('fighter_positions', [
            'fight_id' => $battleModel->id,
            'user_id' => $userTwo->id,
            'x' => 2,
            'y' => 1,
        ]);
    }
}
