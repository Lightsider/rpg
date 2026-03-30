<?php

declare(strict_types=1);

namespace App\Application\Chat;

use App\Domain\Chat\Repositories\ChatRepositoryInterface;
use App\Domain\Character\Repositories\CharacterRepositoryInterface;
use App\Domain\DomainException;

class GetPrivateChats
{
    public function __construct(
        private readonly ChatRepositoryInterface $chatRepository,
        private readonly CharacterRepositoryInterface $characterRepository
    ) {
    }

    /**
     * @return array<int, array{chat_id:int, participant:array{id:int,name:string}}>
     */
    public function execute(int $characterId): array
    {
        $character = $this->characterRepository->findById($characterId);
        if (!$character) {
            throw new DomainException('Character not found.');
        }

        $chats = $this->chatRepository->listPrivateChatsForCharacter($characterId);
        $otherIds = [];
        foreach ($chats as $chat) {
            foreach ($chat->getParticipants() as $participantId) {
                if ($participantId !== $characterId) {
                    $otherIds[] = $participantId;
                }
            }
        }

        $otherIds = array_values(array_unique($otherIds));
        $others = $this->characterRepository->findManyByIds($otherIds);
        $otherMap = [];
        foreach ($others as $other) {
            $otherMap[$other->getId()] = $other;
        }

        $result = [];
        foreach ($chats as $chat) {
            $otherId = null;
            foreach ($chat->getParticipants() as $participantId) {
                if ($participantId !== $characterId) {
                    $otherId = $participantId;
                    break;
                }
            }
            if ($otherId === null || !isset($otherMap[$otherId])) {
                continue;
            }

            $result[] = [
                'chat_id' => $chat->getId(),
                'participant' => [
                    'id' => $otherMap[$otherId]->getId(),
                    'name' => $otherMap[$otherId]->getName(),
                ],
            ];
        }

        return $result;
    }
}
