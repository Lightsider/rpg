<?php

declare(strict_types=1);

namespace App\Infrastructure\Eloquent\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NpcTemplateModel extends Model
{
    use HasFactory;

    protected $table = 'npc_templates';

    protected $fillable = [
        'name',
        'type',
        'strength',
        'agility',
        'constitution',
        'wit',
        'level',
        'behavior_model',
        'equipment_config',
    ];

    protected $casts = [
        'strength' => 'integer',
        'agility' => 'integer',
        'constitution' => 'integer',
        'wit' => 'integer',
        'level' => 'integer',
        'equipment_config' => 'array',
    ];
}
