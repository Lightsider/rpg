<?php

declare(strict_types=1);

namespace App\Events\Chat;

use App\Domain\Chat\ChatType;
use DateTimeImmutable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChatMessageSent implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly ChatType $chatType,
        public readonly int $contextId,
        public readonly int $chatId,
        public readonly int $messageId,
        public readonly int $senderId,
        public readonly string $senderName,
        public readonly string $message,
        public readonly DateTimeImmutable $timestamp
    ) {
    }

    public function broadcastOn(): array
    {
        return match ($this->chatType) {
            ChatType::LOCATION => [new PrivateChannel('chat.location.' . $this->contextId)],
            ChatType::BATTLE => [new PrivateChannel('chat.battle.' . $this->contextId)],
            ChatType::PRIVATE => [new PrivateChannel('chat.private.' . $this->chatId)],
        };
    }

    public function broadcastAs(): string
    {
        return 'chat.message';
    }

    public function broadcastWith(): array
    {
        return [
            'type' => $this->broadcastAs(),
            'payload' => [
                'chatType' => $this->chatType->value,
                'contextId' => $this->contextId,
                'chatId' => $this->chatId,
                'messageId' => $this->messageId,
                'sender' => [
                    'id' => $this->senderId,
                    'name' => $this->senderName,
                ],
                'message' => $this->message,
                'timestamp' => $this->timestamp->format(DATE_ATOM),
            ],
        ];
    }
}
