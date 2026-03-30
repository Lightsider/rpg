<?php

declare(strict_types=1);

namespace App\Infrastructure\Eloquent\Repositories;

use App\Domain\Chat\Chat;
use App\Domain\Chat\ChatType;
use App\Domain\Chat\Repositories\ChatRepositoryInterface;
use App\Infrastructure\Eloquent\Models\ChatModel;

class EloquentChatRepository implements ChatRepositoryInterface
{
    public function findByTypeAndContext(ChatType $type, int $contextId): ?Chat
    {
        $model = ChatModel::with('participants')
            ->where('type', $type->value)
            ->where('context_id', $contextId)
            ->first();

        return $model ? $this->mapToDomain($model) : null;
    }

    public function findPrivateChatBetween(int $firstCharacterId, int $secondCharacterId): ?Chat
    {
        $model = ChatModel::with('participants')
            ->where('type', ChatType::PRIVATE->value)
            ->whereHas('participants', function ($query) use ($firstCharacterId) {
                $query->where('character_id', $firstCharacterId);
            })
            ->whereHas('participants', function ($query) use ($secondCharacterId) {
                $query->where('character_id', $secondCharacterId);
            })
            ->first();

        return $model ? $this->mapToDomain($model) : null;
    }

    public function create(ChatType $type, ?int $contextId, array $participantIds = []): Chat
    {
        $model = ChatModel::create([
            'type' => $type->value,
            'context_id' => $contextId,
        ]);

        if (count($participantIds) > 0) {
            $model->participants()->sync($participantIds);
            $model->load('participants');
        }

        return $this->mapToDomain($model);
    }

    public function getParticipants(int $chatId): array
    {
        $model = ChatModel::with('participants')->find($chatId);
        if (!$model) {
            return [];
        }

        return $model->participants->pluck('id')->map(fn($id) => (int) $id)->all();
    }

    public function listPrivateChatsForCharacter(int $characterId): array
    {
        $models = ChatModel::with('participants')
            ->where('type', ChatType::PRIVATE->value)
            ->whereHas('participants', function ($query) use ($characterId) {
                $query->where('character_id', $characterId);
            })
            ->get();

        return $models->map(fn(ChatModel $model) => $this->mapToDomain($model))->all();
    }

    private function mapToDomain(ChatModel $model): Chat
    {
        $participants = $model->relationLoaded('participants')
            ? $model->participants->pluck('id')->map(fn($id) => (int) $id)->all()
            : [];

        return new Chat(
            id: $model->id,
            type: ChatType::from($model->type),
            contextId: $model->context_id !== null ? (int) $model->context_id : null,
            participants: $participants
        );
    }
}
