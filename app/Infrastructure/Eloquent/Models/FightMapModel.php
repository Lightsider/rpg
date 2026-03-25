<?php

declare(strict_types=1);

namespace App\Infrastructure\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FightMapModel extends Model
{
    protected $table = 'fight_maps';

    protected $fillable = [
        'fight_id',
        'width',
        'height',
    ];

    protected $casts = [
        'fight_id' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
    ];

    public function battle(): BelongsTo
    {
        return $this->belongsTo(BattleModel::class, 'fight_id');
    }
}
