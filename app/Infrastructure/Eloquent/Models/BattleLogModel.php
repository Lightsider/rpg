<?php

declare(strict_types=1);

namespace App\Infrastructure\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BattleLogModel extends Model
{
    protected $table = 'battle_logs';

    protected $fillable = [
        'battle_id',
        'round_number',
        'type',
        'actor_id',
        'target_id',
        'damage',
        'zone',
        'outcome',
        'is_crit',
        'is_max',
        'occurred_at',
    ];

    protected $casts = [
        'occurred_at' => 'immutable_datetime',
        'is_crit' => 'boolean',
        'is_max' => 'boolean',
    ];

    public function battle(): BelongsTo
    {
        return $this->belongsTo(BattleModel::class, 'battle_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(CharacterModel::class, 'actor_id');
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(CharacterModel::class, 'target_id');
    }
}
