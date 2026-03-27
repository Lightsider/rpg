<?php

declare(strict_types=1);

namespace App\Infrastructure\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Infrastructure\Eloquent\Models\CharacterModel;

class BattleModel extends Model
{
    protected $table = 'battles';

    protected $fillable = [
        'state',
        'location_id',
        'round_number',
        'round_started_at',
        'round_duration_seconds',
        'max_participants',
        'start_timeout_seconds',
        'committed_character_ids',
        'map_width',
        'map_height',
    ];

    protected $casts = [
        'round_started_at' => 'immutable_datetime',
        'committed_character_ids' => 'array',
        'round_number' => 'integer',
        'round_duration_seconds' => 'integer',
        'max_participants' => 'integer',
        'start_timeout_seconds' => 'integer',
        'map_width' => 'integer',
        'map_height' => 'integer',
    ];

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(CharacterModel::class, 'battle_participants', 'battle_id', 'character_id')
            ->withPivot('team');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(BattleActionModel::class, 'battle_id');
    }

    public function map(): HasOne
    {
        return $this->hasOne(FightMapModel::class, 'fight_id');
    }

    public function fighterPositions(): HasMany
    {
        return $this->hasMany(FighterPositionModel::class, 'fight_id');
    }
}
