<?php

declare(strict_types=1);

namespace App\Infrastructure\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FighterPositionModel extends Model
{
    protected $table = 'fighter_positions';

    protected $fillable = [
        'fight_id',
        'user_id',
        'x',
        'y',
    ];

    protected $casts = [
        'fight_id' => 'integer',
        'user_id' => 'integer',
        'x' => 'integer',
        'y' => 'integer',
    ];

    public function battle(): BelongsTo
    {
        return $this->belongsTo(BattleModel::class, 'fight_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
