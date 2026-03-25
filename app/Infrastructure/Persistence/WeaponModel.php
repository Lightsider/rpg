<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Weapon\DamageType;
use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent model for the weapons table.
 */
class WeaponModel extends Model
{
    protected $table = 'weapons';

    protected $fillable = [
        'name',
        'min_damage',
        'max_damage',
        'damage_type',
        'accuracy_bonus',
        'block_break_chance',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'damage_type' => DamageType::class,
            'min_damage' => 'integer',
            'max_damage' => 'integer',
            'accuracy_bonus' => 'float',
            'block_break_chance' => 'float',
        ];
    }
}
