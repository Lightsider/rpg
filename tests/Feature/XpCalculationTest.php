<?php

namespace Tests\Feature;

use App\Domain\Battle\ActionType;
use App\Domain\Battle\BattleState;
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

class XpCalculationTest extends TestCase
{
    use RefreshDatabase;

    public function test_round_resolver_calculates_effectiveness_xp(): void
    {
        $location = LocationModel::factory()->create();
        
        $user1 = User::factory()->create();
        $char1 = CharacterModel::factory()->create([
            'user_id' => $user1->id,
            'level' => 1,
            'experience' => 0,
            'dexterity' => 0,
            'hp' => 100,
            'max_hp' => 100,
            'location_id' => $location->id,
        ]);

        $user2 = User::factory()->create();
        $char2 = CharacterModel::factory()->create([
            'user_id' => $user2->id,
            'level' => 1,
            'experience' => 0,
            'dexterity' => 0,
            'hp' => 100,
            'max_hp' => 100,
            'location_id' => $location->id,
        ]);

        // Create a battle
        $battleModel = BattleModel::factory()->create([
            'location_id' => $location->id,
            'state' => 'active',
        ]);
        // Place characters
        $char1->update(['x' => 0, 'y' => 0]);
        $char2->update(['x' => 0, 'y' => 1]);

        // Equip char1 with a weapon
        $weapon = ItemModel::factory()->create([
            'type' => 'weapon',
            'min_damage' => 10,
            'max_damage' => 20,
        ]);
        $char1->update(['weapon_id' => $weapon->id]);

        $battleModel->participants()->attach([
            $char1->id => ['team' => '1'],
            $char2->id => ['team' => '2'],
        ]);

        // Load into Domain
        /** @var \App\Domain\Battle\Repositories\BattleRepositoryInterface $repo */
        $repo = app(\App\Domain\Battle\Repositories\BattleRepositoryInterface::class);
        $battle = $repo->findById($battleModel->id);

        // Set char2 AP to 0 so they don't auto-block everything
        foreach ($battle->getParticipants() as $p) {
            if ($p->getId() === $char2->id) {
                $p->spendAP($p->getCurrentActionPoints());
            }
        }

        // Queue an attack from char1 to char2
        $battle->queueAction(new TurnAction($char1->id, ActionType::ATTACK, TargetZone::TORSO, $char2->id));
        
        // Resolve round
        /** @var \App\Domain\Battle\RoundResolver $resolver */
        $resolver = app(RoundResolverInterface::class);
        
        $res = $resolver->resolve($battle);

        // Check if effectiveness was added to domain characters during resolution
        $participants = $battle->getParticipants();
        $c1 = null; $c2 = null;
        foreach ($participants as $p) {
            if ($p->getId() === $char1->id) $c1 = $p;
            if ($p->getId() === $char2->id) $c2 = $p;
        }

        if ($c1->getEffectiveness() + $c2->getEffectiveness() == 0) {
            foreach ($res->logs as $log) {
                dump("Log: Type={$log->type->value}, Actor={$log->actorId}, Outcome={$log->outcome}, Damage={$log->damage}");
            }
        }

        // Expect some effectiveness if attack landed
        $this->assertGreaterThan(0, $c1->getEffectiveness() + $c2->getEffectiveness(), "Effectiveness should be generated if damage is dealt.");

        // Now force battle end to check XP distribution
        $c2->setCurrentHp(0);
        $resolver->resolve($battle);

        $this->assertTrue($battle->isFinished());
        $rewards = $battle->getRewards();
        
        $this->assertArrayHasKey($char1->id, $rewards);
        $this->assertArrayHasKey($char2->id, $rewards);
        
        $xp1 = $rewards[$char1->id]->xp;
        $xp2 = $rewards[$char2->id]->xp;

        $this->assertGreaterThan(0, $xp1, "Winner should have XP.");
        $this->assertGreaterThan(0, $xp2, "Loser should have XP (50% of base).");
        
        // Winner (char1) should have more XP than if they were a loser with same effectiveness
        // In this symmetric 1-hit case, effectiveness should be equal if armor is same
        // But winner gets 100% and loser gets 50%
        $this->assertEquals((int)round($xp1 * 0.5), $xp2, "Loser should have exactly half XP of winner for equal effectiveness.");
    }
}
