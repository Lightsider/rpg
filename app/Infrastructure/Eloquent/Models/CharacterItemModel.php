<?php

namespace App\Infrastructure\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CharacterItemModel extends Model
{
    protected $table = 'character_items';

    protected $fillable = [
        'character_id',
        'item_id',
        'quantity',
    ];

    protected $casts = [
        'quantity' => 'int',
    ];

    public function character(): BelongsTo
    {
        return $this->belongsTo(CharacterModel::class, 'character_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(ItemModel::class, 'item_id');
    }
}
