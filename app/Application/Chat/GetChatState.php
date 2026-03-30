<?php

declare(strict_types=1);

namespace App\Application\Chat;

use App\Domain\Battle\Repositories\BattleRepositoryInterface;
use App\Domain\Chat\ChatType;
use App\Domain\Chat\Repositories\ChatMessageRepositoryInterface;
use App\Domain\Chat\Repositories\ChatRepositoryInterface;
use App\Domain\Character\Repositories\CharacterRepositoryInterface;
use App\Domain\DomainException;

class GetChatState
{
    public function __construct(
        private readonly ChatRepositoryInterface $chatRepository,
        private readonly ChatMessageRepositoryInterface $chatMessageRepository,
        private readonly CharacterRepositoryInterface $characterRepository,
        private readonly BattleRepositoryInterface $battleRepository
    ) {
    }

    /**
     * @return array{chat_id:int|null,messages:array,participants:array}
     */
    public function execute(int $characterId, ChatType $chatType, int $contextId): array
    {
        $character = $this->characterRepository->findById($characterId);
        if (!$character) {
            throw new DomainException('Character not found.');
        }

        if ($chatType === ChatType::LOCATION) {
            if ($character->getLocationId() !== $contextId) {
                throw new DomainException('Character is not in this location.');
            }

            $chat = $this->chatRepository->findByTypeAndContext($chatType, $contextId)
                ?? $this->chatRepository->create($chatType, $contextId);

            $participants = $this->characterRepository->findByLocationId($contextId);
            return [
                'chat_id' => $chat->getId(),
                'messages' => $this->serializeRecentMessagesAfter($chat->getId(), new \DateTimeImmutable('-24 hours')),
                'participants' => $this->serializeParticipants($participants),
            ];
        }

        if ($chatType === ChatType::BATTLE) {
            $battle = $this->battleRepository->findById($contextId);
            if (!$battle) {
                throw new DomainException('Battle not found.');
            }
            if (!$battle->getParticipantById($characterId)) {
                throw new DomainException('You are not a participant in this battle.');
            }

            $chat = $this->chatRepository->findByTypeAndContext($chatType, $contextId)
                ?? $this->chatRepository->create($chatType, $contextId);

            $participants = array_values($battle->getParticipants());
            return [
                'chat_id' => $chat->getId(),
                'messages' => $this->serializeMessages($chat->getId()),
                'participants' => $this->serializeParticipants($participants),
            ];
        }

        if ($chatType === ChatType::PRIVATE) {
            $target = $this->characterRepository->findById($contextId);
            if (!$target) {
                throw new DomainException('Target character not found.');
            }

            $chat = $this->chatRepository->findPrivateChatBetween($characterId, $contextId);
            if (!$chat) {
                return [
                    'chat_id' => null,
                    'messages' => [],
                    'participants' => $this->serializeParticipants([$character, $target]),
                ];
            }

            $participantIds = $chat->getParticipants();
            $participants = $this->characterRepository->findManyByIds($participantIds);

            return [
                'chat_id' => $chat->getId(),
                'messages' => $this->serializeMessages($chat->getId()),
                'participants' => $this->serializeParticipants($participants),
            ];
        }

        throw new DomainException('Unsupported chat type.');
    }

    private function serializeMessages(int $chatId): array
    {
        return array_values(array_map(
            fn($message) => $message->jsonSerialize(),
            $this->chatMessageRepository->findRecent($chatId, 50)
        ));
    }

    private function serializeRecentMessagesAfter(int $chatId, \DateTimeImmutable $since): array
    {
        return array_values(array_map(
            fn($message) => $message->jsonSerialize(),
            $this->chatMessageRepository->findRecentAfter($chatId, $since)
        ));
    }

    private function serializeParticipants(array $participants): array
    {
        return array_values(array_map(
            fn($participant) => [
                'id' => $participant->getId(),
                'name' => $participant->getName(),
            ],
            $participants
        ));
    }
}
