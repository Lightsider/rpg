<?php

declare(strict_types=1);

namespace App\Application\Chat;

use App\Domain\Chat\ChatMessage;

class ChatMessagePresenter
{
    /**
     * @return array<string, mixed>
     */
    public function present(ChatMessage $message): array
    {
        return [
            'id' => $message->getId(),
            'chat_id' => $message->getChatId(),
            'sender_id' => $message->getSenderId(),
            'message' => $message->getMessage(),
            'created_at' => $message->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}

