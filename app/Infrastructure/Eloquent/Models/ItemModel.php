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
        'flat_crit_bonus',
    ];
}