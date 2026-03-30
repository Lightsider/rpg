<?php

declare(strict_types=1);

namespace App\Infrastructure\Eloquent\Repositories;

use App\Domain\Chat\ChatMessage;
use App\Domain\Chat\Repositories\ChatMessageRepositoryInterface;
use App\Infrastructure\Eloquent\Models\ChatMessageModel;
use DateTimeImmutable;

class EloquentChatMessageRepository implements ChatMessageRepositoryInterface
{
    public function save(ChatMessage $message): ChatMessage
    {
        $model = ChatMessageModel::create([
            'chat_id' => $message->getChatId(),
            'sender_id' => $message->getSenderId(),
            'message' => $message->getMessage(),
            'created_at' => $message->getCreatedAt(),
        ]);

        return new ChatMessage(
            id: $model->id,
            chatId: (int) $model->chat_id,
            senderId: (int) $model->sender_id,
            message: (string) $model->message,
            createdAt: new DateTimeImmutable($model->created_at->toDateTimeString())
        );
    }

    public function findRecent(int $chatId, int $limit = 50): array
    {
        $models = ChatMessageModel::where('chat_id', $chatId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->reverse();

        return $models->map(function (ChatMessageModel $model) {
            return new ChatMessage(
                id: $model->id,
                chatId: (int) $model->chat_id,
                senderId: (int) $model->sender_id,
                message: (string) $model->message,
                createdAt: new DateTimeImmutable($model->created_at->toDateTimeString())
            );
        })->values()->all();
    }

    public function findRecentAfter(int $chatId, \DateTimeImmutable $since): array
    {
        $models = ChatMessageModel::where('chat_id', $chatId)
            ->where('created_at', '>=', $since->format('Y-m-d H:i:s'))
            ->orderBy('created_at', 'asc')
            ->get();

        return $models->map(function (ChatMessageModel $model) {
            return new ChatMessage(
                id: $model->id,
                chatId: (int) $model->chat_id,
                senderId: (int) $model->sender_id,
                message: (string) $model->message,
                createdAt: new DateTimeImmutable($model->created_at->toDateTimeString())
            );
        })->values()->all();
    }
}
