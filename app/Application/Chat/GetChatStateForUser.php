<?php

declare(strict_types=1);

namespace App\Application\Chat;

use App\Domain\Chat\ChatType;
use App\Domain\Character\Repositories\CharacterRepositoryInterface;
use App\Domain\DomainException;

class GetChatStateForUser
{
    public function __construct(
        private readonly CharacterRepositoryInterface $characterRepository,
        private readonly GetChatState $getChatState
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function execute(int $userId, ChatType $chatType, int $contextId): array
    {
        $character = $this->characterRepository->findByUserId($userId);
        if (!$character) {
            throw new DomainException('Character not found.');
        }

        return $this->getChatState->execute(
            $character->getId(),
            $chatType,
            $contextId
        );
    }
}
