<?php

namespace App\Infrastructure\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Infrastructure\Eloquent\Models\ItemModel;
use App\Infrastructure\Eloquent\Models\CharacterItemModel;

class CharacterModel extends Model
{
    protected $table = 'characters';

    protected $fillable = [
        'user_id',
        'name',
        'strength',
        'dexterity',
        'constitution',
        'wit',
        'weapon_id',
        'weapon',
        'backpack_seeded',
        'hp',
        'max_hp',
        'location_id',
        'x',
        'y',
        'currency_copper',
        'damage_accumulator',
        'seal_1_id',
        'seal_2_id',
        'seal_3_id',
        'seal_4_id',
    ];

    protected $casts = [
        'backpack_seeded' => 'bool',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(LocationModel::class, 'location_id');
    }

    public function weaponItem(): BelongsTo
    {
        return $this->belongsTo(ItemModel::class, 'weapon_id');
    }

    public function seal1(): BelongsTo { return $this->belongsTo(ItemModel::class, 'seal_1_id'); }
    public function seal2(): BelongsTo { return $this->belongsTo(ItemModel::class, 'seal_2_id'); }
    public function seal3(): BelongsTo { return $this->belongsTo(ItemModel::class, 'seal_3_id'); }
    public function seal4(): BelongsTo { return $this->belongsTo(ItemModel::class, 'seal_4_id'); }

    public function backpackItems(): HasMany
    {
        return $this->hasMany(CharacterItemModel::class, 'character_id');
    }
}
