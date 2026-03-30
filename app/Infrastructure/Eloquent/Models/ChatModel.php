<?php

declare(strict_types=1);

namespace App\Infrastructure\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ChatModel extends Model
{
    protected $table = 'chats';

    protected $fillable = [
        'type',
        'context_id',
    ];

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(
            CharacterModel::class,
            'chat_participants',
            'chat_id',
            'character_id'
        );
    }
}
