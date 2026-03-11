<?php

declare(strict_types=1);

namespace App\Infrastructure\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BattleActionModel extends Model
{
    protected $table = 'battle_actions';

    protected $fillable = [
        'battle_id',
        'user_id',
        'type',
        'target_zone',
        'from_x',
        'from_y',
        'to_x',
        'to_y',
        'round_number',
        'blocks',
    ];

    protected $casts = [
        'battle_id' => 'integer',
        'user_id' => 'integer',
        'from_x' => 'integer',
        'from_y' => 'integer',
        'to_x' => 'integer',
        'to_y' => 'integer',
        'round_number' => 'integer',
        'blocks' => 'array',
    ];

    public function battle(): BelongsTo
    {
        return $this->belongsTo(BattleModel::class, 'battle_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}


