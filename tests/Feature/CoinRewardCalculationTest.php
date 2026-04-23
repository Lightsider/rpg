<?php

namespace Tests\Feature;

use App\Domain\Battle\ActionType;
use App\Domain\Battle\TargetZone;
use App\Domain\Battle\TurnAction;
use App\Domain\Battle\RoundResolverInterface;
use App\Infrastructure\Eloquent\Models\BattleModel;
use App\Infrastructure\Eloquent\Models\CharacterModel;
use App\Infrastructure\Eloquent\Models\ItemModel;
use App\Infrastructure\Eloquent\Models\LocationModel;
use App\Infrastructure\Eloquent\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoinRewardCalculationTest extends TestCase
{
    use RefreshDatabase;

    public function test_coin_reward_calculation_and_distribution(): void
    {
        $location = LocationModel::factory()->create();
        
        // Character 1 (Winner Team)
        $user1 = User::factory()->create();
        $char1 = CharacterModel::factory()->create([
            'user_id' => $user1->id,
            'hp' => 100,
            'max_hp' => 100,
            'location_id' => $location->id,
            'currency_copper' => 0,
        ]);

        // Character 2 (Loser Team)
        $user2 = User::factory()->create();
        $char2 = CharacterModel::factory()->create([
            'user_id' => $user2->id,
            'hp' => 100,
            'max_hp' => 100,
            'location_id' => $location->id,
            'currency_copper' => 0,
        ]);

        // Equip Char 1 with 1H weapon (multiplier x2) + Chest (multiplier x2) + 2 other items (base x1)
        // Expected contribution: (10 * 2) + (10 * 2) + (10 * 1) + (10 * 1) = 60
        $weapon1h = ItemModel::factory()->create(['type' => 'weapon', 'is_two_handed' => false]);
        $chest = ItemModel::factory()->create(['type' => 'armor', 'armor_subtype' => 'body']);
        $helm = ItemModel::factory()->create(['type' => 'armor', 'armor_subtype' => 'helmet']);
        $boots = ItemModel::factory()->create(['type' => 'armor', 'armor_subtype' => 'boots']);

        $char1->update(['weapon_id' => $weapon1h->id, 'chest_id' => $chest->id, 'helmet_id' => $helm->id, 'legs_id' => $boots->id]);

        // Equip Char 2 with 2H weapon (multiplier x3)
        // Expected contribution: (10 * 3) = 30
        // Total Fund = 60 + 30 = 90
        $weapon2h = ItemModel::factory()->create(['type' => 'weapon', 'is_two_handed' => true]);
        $char2->update(['weapon_id' => $weapon2h->id]);

        // Create a battle
        $battleModel = BattleModel::factory()->create([
            'location_id' => $location->id,
            'state' => 'active',
        ]);
        $battleModel->participants()->attach([
            $char1->id => ['team' => 'win_team'],
            $char2->id => ['team' => 'lose_team'],
        ]);

        // Load into Domain
        $repo = app(\App\Domain\Battle\Repositories\BattleRepositoryInterface::class);
        $battle = $repo->findById($battleModel->id);

        // Force Char 1 to have some effectiveness
        $c1 = null; $c2 = null;
        foreach ($battle->getParticipants() as $p) {
            if ($p->getId() === $char1->id) $c1 = $p;
            if ($p->getId() === $char2->id) $c2 = $p;
        }
        $c1->addEffectiveness(100.0);
        $c2->addEffectiveness(50.0);

        // Force battle end (Char 2 dies)
        $c2->setCurrentHp(0);

        // Resolve
        $resolver = app(RoundResolverInterface::class);
        $resolver->resolve($battle);

        // Verify Total Fund: 60 + 30 = 90
        // Winners (70%): 63 coins
        // Losers (30%): 27 coins

        // Individual share:
        // Char 1 is only winner: gets 63 coins
        // Char 2 is only loser: gets 27 coins

        $rewards = $battle->getRewards();
        $this->assertEquals(63, $rewards[$char1->id]->copper, "Winner should get 70% of 90 coins.");
        $this->assertEquals(27, $rewards[$char2->id]->copper, "Loser should get 30% of 90 coins.");

        // Check persistence
        $this->assertEquals(63, $char1->fresh()->currency_copper);
        $this->assertEquals(27, $char2->fresh()->currency_copper);
    }

    public function test_equal_split_on_zero_effectiveness(): void
    {
        $location = LocationModel::factory()->create();
        
        $user1 = User::factory()->create();
        $char1 = CharacterModel::factory()->create(['user_id' => $user1->id, 'location_id' => $location->id]);
        $user2 = User::factory()->create();
        $char2 = CharacterModel::factory()->create(['user_id' => $user2->id, 'location_id' => $location->id]);

        // No equipment -> 0 coins contribution from power, but base config might still give something if there are default items?
        // Let's ensure they have no items.
        $char1->update(['weapon_id' => null, 'chest_id' => null]);
        $char2->update(['weapon_id' => null, 'chest_id' => null]);

        // Actually let's give them 1 item each so fund is 20.
        $item = ItemModel::factory()->create(['type' => 'weapon', 'is_two_handed' => false]);
        $char1->update(['weapon_id' => $item->id]);
        $char2->update(['weapon_id' => $item->id]);
        // Total Fund: (10*2) + (10*2) = 40.

        $battleModel = BattleModel::factory()->create(['location_id' => $location->id, 'state' => 'active']);
        // Two characters on the same team vs no one? No, needs a loser to finish.
        $user3 = User::factory()->create();
        $char3 = CharacterModel::factory()->create(['user_id' => $user3->id, 'location_id' => $location->id]);
        $char3->update(['weapon_id' => $item->id]);
        // Total Fund: 40 + 20 = 60.

        $battleModel->participants()->attach([
            $char1->id => ['team' => 'team_a'],
            $char2->id => ['team' => 'team_a'],
            $char3->id => ['team' => 'team_b'],
        ]);

        $repo = app(\App\Domain\Battle\Repositories\BattleRepositoryInterface::class);
        $battle = $repo->findById($battleModel->id);

        // Team A (Winners) has 0 total effectiveness
        // Team B (Loser) dies
        foreach ($battle->getParticipants() as $p) {
            if ($p->getId() === $char3->id) $p->setCurrentHp(0);
        }

        $resolver = app(RoundResolverInterface::class);
        $resolver->resolve($battle);

        // Winner Share (70% of 60): 42 coins
        // Char 1 and Char 2 share 42 coins equally: 21 each
        $rewards = $battle->getRewards();
        $this->assertEquals(21, $rewards[$char1->id]->copper);
        $this->assertEquals(21, $rewards[$char2->id]->copper);
    }
    public function test_dagger_and_shield_have_1x_multiplier(): void
    {
        $location = LocationModel::factory()->create();
        $user = User::factory()->create();
        $char = CharacterModel::factory()->create(['user_id' => $user->id, 'location_id' => $location->id]);

        // Dagger (offhand_weapon) + Shield (shield) + 1 regular armor
        // Fund should be: (10 * 1) + (10 * 1) + (10 * 1) = 30
        $dagger = ItemModel::factory()->create(['type' => 'offhand_weapon']);
        $shield = ItemModel::factory()->create(['type' => 'shield']);
        $helm = ItemModel::factory()->create(['type' => 'armor', 'armor_subtype' => 'helmet']);

        $char->update(['off_hand_id' => $dagger->id, 'helmet_id' => $helm->id]);
        
        $battleModel = BattleModel::factory()->create(['location_id' => $location->id, 'state' => 'active']);
        $battleModel->participants()->attach([$char->id => ['team' => 'win_team']]);

        $repo = app(\App\Domain\Battle\Repositories\BattleRepositoryInterface::class);
        $battle = $repo->findById($battleModel->id);
        $p = array_values($battle->getParticipants())[0];

        // 1. Dagger + Helm = 20
        $this->assertEquals(20, $p->calculateCoinContribution(10, ['weapon_1h' => 2]));

        // 2. Shield + Helm = 20
        $char->update(['off_hand_id' => $shield->id]);
        $battle = $repo->findById($battleModel->id);
        $p = array_values($battle->getParticipants())[0];
        $this->assertEquals(20, $p->calculateCoinContribution(10, ['body' => 2]));
    }
}
