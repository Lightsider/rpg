<?php

declare(strict_types=1);

namespace App\Infrastructure\WebSockets\Handlers;

use App\Application\Chat\SendMessage;
use App\Domain\Chat\ChatType;
use App\Domain\Character\Repositories\CharacterRepositoryInterface;
use App\Domain\DomainException;
use PHPUnit\Framework\Attributes\CodeCoverageIgnore;

class ChatMessageHandler
{
    public function __construct(
        private readonly SendMessage $sendMessage,
        private readonly CharacterRepositoryInterface $characterRepository
    ) {
    }

    public function handle(int $userId, array $payload): void
    {
        $chatType = $payload['chatType'] ?? null;
        $contextId = $payload['contextId'] ?? null;
        $message = $payload['message'] ?? null;

        if (!$chatType || !$contextId || !$message) {
            return;
        }

        $character = $this->characterRepository->findByUserId($userId);
        if (!$character) {
            return;
        }

        try {
            $this->sendMessage->execute(
                $character->getId(),
                ChatType::from($chatType),
                (int) $contextId,
                (string) $message
            );
        } catch (DomainException) {
            // Log or handle error if needed
        }
    }
}
