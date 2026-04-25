<?php

namespace App\Infrastructure\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

class LevelSublevelModel extends Model
{
    protected $table = 'level_sublevels';

    protected $fillable = [
        'level',
        'sublevel_index',
        'xp_threshold',
        'reward_copper',
    ];

    public $incrementing = false;
    protected $primaryKey = ['level', 'sublevel_index'];

    // Note: Laravel doesn't support composite primary keys out of the box for some operations,
    // but for simple lookups it's fine.
}
