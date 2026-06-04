<?php

namespace App\Infrastructure\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Infrastructure\Eloquent\Models\ItemModel;
use App\Infrastructure\Eloquent\Models\CharacterItemModel;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class CharacterModel extends Model
{
    use HasFactory;

    protected static function newFactory()
    {
        return \Database\Factories\CharacterModelFactory::new();
    }
    protected $table = 'characters';

    protected $fillable = [
        'user_id',
        'name',
        'level',
        'experience',
        'sublevel_index',
        'unallocated_stats',
        'strength',
        'dexterity',
        'constitution',
        'wit',
        'weapon_id',
        'off_hand_id',
        'weapon',
        'backpack_seeded',
        'hp',
        'max_hp',
        'location_id',
        'x',
        'y',
        'currency_copper',
        'damage_accumulator',
        'effectiveness',
        'seal_1_id',
        'seal_2_id',
        'seal_3_id',
        'seal_4_id',
        'helmet_id',
        'chest_id',
        'legs_id',
        'gloves_id',
        'ad_armor_head',
        'ad_armor_chest',
        'ad_armor_legs',
        'ad_armor_hands',
        'ad_armor_left_arm',
        'ad_armor_right_arm',
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

    public function offHand(): BelongsTo
    {
        return $this->belongsTo(ItemModel::class, 'off_hand_id');
    }

    public function seal1(): BelongsTo { return $this->belongsTo(ItemModel::class, 'seal_1_id'); }
    public function seal2(): BelongsTo { return $this->belongsTo(ItemModel::class, 'seal_2_id'); }
    public function seal3(): BelongsTo { return $this->belongsTo(ItemModel::class, 'seal_3_id'); }
    public function seal4(): BelongsTo { return $this->belongsTo(ItemModel::class, 'seal_4_id'); }

    public function helmet(): BelongsTo { return $this->belongsTo(ItemModel::class, 'helmet_id'); }
    public function chest(): BelongsTo { return $this->belongsTo(ItemModel::class, 'chest_id'); }
    public function legs(): BelongsTo { return $this->belongsTo(ItemModel::class, 'legs_id'); }
    public function gloves(): BelongsTo { return $this->belongsTo(ItemModel::class, 'gloves_id'); }

    public function backpackItems(): HasMany
    {
        return $this->hasMany(CharacterItemModel::class, 'character_id');
    }
}
