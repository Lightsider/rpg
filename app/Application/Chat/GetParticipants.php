<?php

declare(strict_types=1);

namespace App\Application\Chat;

use App\Domain\Battle\Repositories\BattleRepositoryInterface;
use App\Domain\Chat\ChatType;
use App\Domain\Chat\Repositories\ChatRepositoryInterface;
use App\Domain\Character\Repositories\CharacterRepositoryInterface;
use App\Domain\DomainException;

class GetParticipants
{
    public function __construct(
        private readonly ChatRepositoryInterface $chatRepository,
        private readonly CharacterRepositoryInterface $characterRepository,
        private readonly BattleRepositoryInterface $battleRepository
    ) {
    }

    /**
     * @return array
     */
    public function execute(ChatType $chatType, int $contextId): array
    {
        $participants = match ($chatType) {
            ChatType::LOCATION => $this->characterRepository->findByLocationId($contextId),
            ChatType::BATTLE => $this->getBattleParticipants($contextId),
            ChatType::PRIVATE => $this->getPrivateParticipants($contextId),
        };

        return array_values(array_map(
            fn($participant) => [
                'id' => $participant->getId(),
                'name' => $participant->getName(),
            ],
            $participants
        ));
    }

    private function getBattleParticipants(int $battleId): array
    {
        $battle = $this->battleRepository->findById($battleId);
        if (!$battle) {
            throw new DomainException('Battle not found.');
        }

        return array_values($battle->getParticipants());
    }

    private function getPrivateParticipants(int $chatId): array
    {
        $chat = $this->chatRepository->findByTypeAndContext(ChatType::PRIVATE, $chatId);
        if (!$chat) {
            // For private chats, the contextId should be the chatId if it exists,
            // but the UI might pass the target character ID if the chat doesn't exist yet.
            // However, GetParticipants usually works on an existing chat canal.
            return [];
        }

        $participantIds = $this->chatRepository->getParticipants($chat->getId());
        return $this->characterRepository->findManyByIds($participantIds);
    }
}
