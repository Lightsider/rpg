<?php

namespace Tests\Feature;

use App\Infrastructure\Eloquent\Models\CharacterModel;
use App\Infrastructure\Eloquent\Models\CharacterItemModel;
use App\Infrastructure\Eloquent\Models\ItemModel;
use App\Infrastructure\Eloquent\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LevelRequirementTest extends TestCase
{
    use RefreshDatabase;

    public function test_character_cannot_equip_item_if_level_is_too_low(): void
    {
        $user = User::factory()->create();
        $character = CharacterModel::factory()->create([
            'user_id' => $user->id,
            'level' => 1,
        ]);

        $highLevelWeapon = ItemModel::factory()->create([
            'name' => 'Dragon Slayer',
            'type' => 'weapon',
            'required_level' => 10,
        ]);

        // Put item in backpack
        CharacterItemModel::create([
            'character_id' => $character->id,
            'item_id' => $highLevelWeapon->id,
            'quantity' => 1
        ]);

        // Attempt to equip it - should fail or be blocked by domain logic
        $response = $this->actingAs($user)->postJson('/api/character/backpack/equip', [
            'item_id' => $highLevelWeapon->id,
            'slot' => 'main_hand'
        ]);

        // Depending on implementation, it might return 422 or just not equip it.
        // The BackpackController catches DomainException and returns 422.
        $response->assertStatus(422);
        $response->assertJsonFragment(['error' => 'You do not meet the requirements for this weapon.']);

        $character->refresh();
        $this->assertNotEquals($highLevelWeapon->id, $character->weapon_id);
    }

    public function test_character_can_equip_item_if_level_is_sufficient(): void
    {
        $user = User::factory()->create();
        $character = CharacterModel::factory()->create([
            'user_id' => $user->id,
            'level' => 10,
        ]);

        $highLevelWeapon = ItemModel::factory()->create([
            'name' => 'Dragon Slayer',
            'type' => 'weapon',
            'required_level' => 10,
        ]);

        // Put item in backpack
        CharacterItemModel::create([
            'character_id' => $character->id,
            'item_id' => $highLevelWeapon->id,
            'quantity' => 1
        ]);

        // Attempt to equip it - should succeed
        $response = $this->actingAs($user)->postJson('/api/character/backpack/equip', [
            'item_id' => $highLevelWeapon->id,
            'slot' => 'main_hand'
        ]);

        $response->assertStatus(200);

        $character->refresh();
        $this->assertEquals($highLevelWeapon->id, $character->weapon_id);
    }

    public function test_character_at_level_1_can_equip_level_1_items(): void
    {
        $user = User::factory()->create();
        $character = CharacterModel::factory()->create([
            'user_id' => $user->id,
            'level' => 1,
        ]);

        $basicWeapon = ItemModel::factory()->create([
            'name' => 'Rusty Sword',
            'type' => 'weapon',
            'required_level' => 1,
        ]);

        // Put item in backpack
        CharacterItemModel::create([
            'character_id' => $character->id,
            'item_id' => $basicWeapon->id,
            'quantity' => 1
        ]);

        // Attempt to equip it - should succeed
        $response = $this->actingAs($user)->postJson('/api/character/backpack/equip', [
            'item_id' => $basicWeapon->id,
            'slot' => 'main_hand'
        ]);

        $response->assertStatus(200);

        $character->refresh();
        $this->assertEquals($basicWeapon->id, $character->weapon_id);
    }

    public function test_seeded_level_2_items_exist_and_enforce_level_2_requirements(): void
    {
        $this->seed(\Database\Seeders\ItemSeeder::class);

        $lvl2Weapon = ItemModel::where('name', 'Steadfast Sword II')->firstOrFail();
        $this->assertEquals(2, $lvl2Weapon->required_level);
        $this->assertEquals(12, $lvl2Weapon->required_strength);

        $user1 = User::factory()->create();
        $level1Char = CharacterModel::factory()->create([
            'user_id' => $user1->id,
            'level' => 1,
            'strength' => 12,
        ]);

        CharacterItemModel::create([
            'character_id' => $level1Char->id,
            'item_id' => $lvl2Weapon->id,
            'quantity' => 1,
        ]);

        $resFail = $this->actingAs($user1)->postJson('/api/character/backpack/equip', [
            'item_id' => $lvl2Weapon->id,
            'slot' => 'main_hand',
        ]);
        $resFail->assertStatus(422);

        $user2 = User::factory()->create();
        $level2Char = CharacterModel::factory()->create([
            'user_id' => $user2->id,
            'level' => 2,
            'strength' => 12,
        ]);

        CharacterItemModel::create([
            'character_id' => $level2Char->id,
            'item_id' => $lvl2Weapon->id,
            'quantity' => 1,
        ]);

        $resSuccess = $this->actingAs($user2)->postJson('/api/character/backpack/equip', [
            'item_id' => $lvl2Weapon->id,
            'slot' => 'main_hand',
        ]);
        $resSuccess->assertStatus(200);

        $level2Char->refresh();
        $this->assertEquals($lvl2Weapon->id, $level2Char->weapon_id);
    }
}
