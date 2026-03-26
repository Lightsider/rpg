<?php

namespace App\Infrastructure\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

class ItemModel extends Model
{
    protected $table = 'items';

    protected $fillable = [
        'name',
        'type',
        'min_damage',
        'max_damage',
        'damage_type',
        'accuracy_bonus',
        'block_break_rating',
        'pierce_multiplier',
        'max_damage_rating',
        'archetype',
        'required_strength',
        'required_wit',
        'required_dexterity',
        'required_constitution',
        'flat_crit_bonus',
        'ad_armor',
        'dodge_bonus',
        'armor_subtype',
    ];

    protected $casts = [
        'min_damage' => 'float',
        'max_damage' => 'float',
        'flat_crit_bonus' => 'float',
        'ad_armor' => 'float',
        'dodge_bonus' => 'float',
    ];
}

