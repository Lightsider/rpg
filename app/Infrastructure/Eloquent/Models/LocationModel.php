<?php

namespace App\Infrastructure\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class LocationModel extends Model
{
    use HasFactory;

    protected static function newFactory()
    {
        return \Database\Factories\LocationModelFactory::new();
    }
    protected $table = 'locations';

    protected $fillable = ['name', 'description', 'max_players', 'start_timeout_seconds'];
}
