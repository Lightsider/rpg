<?php

namespace App\Infrastructure\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Infrastructure\Eloquent\Models\ItemModel;

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
        'hp',
        'max_hp',
        'location_id',
        'x',
        'y',
        'currency_copper'
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
}
