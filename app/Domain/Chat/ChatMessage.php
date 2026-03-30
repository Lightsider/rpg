<?php

declare(strict_types=1);

namespace App\Domain\Chat;

use DateTimeImmutable;

class ChatMessage implements \JsonSerializable
{
    public function __construct(
        private readonly int $id,
        private readonly int $chatId,
        private readonly int $senderId,
        private readonly string $message,
        private readonly DateTimeImmutable $createdAt
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getChatId(): int
    {
        return $this->chatId;
    }

    public function getSenderId(): int
    {
        return $this->senderId;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'chat_id' => $this->chatId,
            'sender_id' => $this->senderId,
            'message' => $this->message,
            'created_at' => $this->createdAt->format(DATE_ATOM),
        ];
    }
}
