<?php

declare(strict_types=1);

namespace App\Services;

use App\Infrastructure\Eloquent\Models\ItemModel;

class InventoryPayloadAssembler
{
    /**
     * @return array<string, mixed>
     */
    public function fromItem(ItemModel $item, int $quantity): array
    {
        return [
            'id' => $item->id,
            'name' => $item->name,
            'type' => $item->type,
            'quantity' => $quantity,
            'min_damage' => $item->min_damage,
            'max_damage' => $item->max_damage,
            'damage_type' => $item->damage_type,
            'accuracy_bonus' => $item->accuracy_bonus,
            'block_break_rating' => $item->block_break_rating,
            'pierce_multiplier' => $item->pierce_multiplier,
            'max_damage_rating' => $item->max_damage_rating,
            'archetype' => $item->archetype,
            'required_strength' => $item->required_strength,
            'required_wit' => $item->required_wit,
            'required_dexterity' => $item->required_dexterity,
            'required_constitution' => $item->required_constitution,
            'flat_crit_bonus' => $item->flat_crit_bonus,
            'crit_chance_bonus' => $item->crit_chance_bonus,
            'ad_armor' => $item->ad_armor,
            'dodge_bonus' => $item->dodge_bonus,
            'armor_subtype' => $item->armor_subtype,
            'defensive_ap_bonus' => $item->defensive_ap_bonus,
            'offhand_ap_bonus' => $item->type === 'offhand_weapon' ? 1 : 0,
            'parry_rating' => $item->parry_rating,
            'required_level' => $item->required_level ?? 1,
        ];
    }
}

