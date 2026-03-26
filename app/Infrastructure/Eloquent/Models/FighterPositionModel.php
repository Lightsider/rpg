<?php

declare(strict_types=1);

namespace App\Infrastructure\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Infrastructure\Eloquent\Models\CharacterModel;

class FighterPositionModel extends Model
{
    protected $table = 'fighter_positions';

    protected $fillable = [
        'fight_id',
        'character_id',
        'x',
        'y',
    ];

    protected $casts = [
        'fight_id' => 'integer',
        'character_id' => 'integer',
        'x' => 'integer',
        'y' => 'integer',
    ];

    public function battle(): BelongsTo
    {
        return $this->belongsTo(BattleModel::class, 'fight_id');
    }

    public function character(): BelongsTo
    {
        return $this->belongsTo(CharacterModel::class, 'character_id');
    }
}
