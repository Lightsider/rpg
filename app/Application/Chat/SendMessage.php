<?php

declare(strict_types=1);

namespace App\Application\Chat;

use App\Application\Contracts\EventDispatcherInterface;
use App\Domain\Battle\Repositories\BattleRepositoryInterface;
use App\Domain\Chat\ChatMessage;
use App\Domain\Chat\ChatType;
use App\Domain\Chat\Repositories\ChatMessageRepositoryInterface;
use App\Domain\Chat\Repositories\ChatRepositoryInterface;
use App\Domain\Character\Repositories\CharacterRepositoryInterface;
use App\Domain\DomainException;
use App\Events\Chat\ChatMessageSent;
use DateTimeImmutable;
use Psr\Log\LoggerInterface;

class SendMessage
{
    public function __construct(
        private readonly ChatRepositoryInterface $chatRepository,
        private readonly ChatMessageRepositoryInterface $chatMessageRepository,
        private readonly CharacterRepositoryInterface $characterRepository,
        private readonly BattleRepositoryInterface $battleRepository,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @return array{chat_id:int,chat_type:string,context_id:int|null,message:array}
     */
    public function execute(int $senderId, ChatType $chatType, int $contextId, string $messageText): array
    {
        $sender = $this->characterRepository->findById($senderId);
        if (!$sender) {
            throw new DomainException('Character not found.');
        }

        if ($chatType === ChatType::LOCATION) {
            if ($sender->getLocationId() !== $contextId) {
                throw new DomainException('Character is not in this location.');
            }

            $chat = $this->chatRepository->findByTypeAndContext($chatType, $contextId)
                ?? $this->chatRepository->create($chatType, $contextId);
        } elseif ($chatType === ChatType::BATTLE) {
            $battle = $this->battleRepository->findById($contextId);
            if (!$battle) {
                throw new DomainException('Battle not found.');
            }
            if (!$battle->getParticipantById($senderId)) {
                throw new DomainException('You are not a participant in this battle.');
            }

            $chat = $this->chatRepository->findByTypeAndContext($chatType, $contextId)
                ?? $this->chatRepository->create($chatType, $contextId);
        } elseif ($chatType === ChatType::PRIVATE) {
            $target = $this->characterRepository->findById($contextId);
            if (!$target) {
                throw new DomainException('Target character not found.');
            }

            $chat = $this->chatRepository->findPrivateChatBetween($senderId, $contextId);
            if (!$chat) {
                $chat = $this->chatRepository->create($chatType, null, [$senderId, $contextId]);
            }
        } else {
            throw new DomainException('Unsupported chat type.');
        }

        $message = new ChatMessage(
            id: 0,
            chatId: $chat->getId(),
            senderId: $senderId,
            message: $messageText,
            createdAt: new DateTimeImmutable()
        );

        $message = $this->chatMessageRepository->save($message);

        try {
            $this->eventDispatcher->dispatch(new ChatMessageSent(
                chatType: $chatType,
                contextId: $contextId,
                chatId: $chat->getId(),
                messageId: $message->getId(),
                senderId: $sender->getId(),
                senderName: $sender->getName(),
                message: $message->getMessage(),
                timestamp: $message->getCreatedAt()
            ));
        } catch (\Throwable $e) {
            $this->logger->error('Broadcast failed.', [
                'exception' => $e,
                'chat_type' => $chatType->value,
                'context_id' => $contextId,
                'sender_id' => $senderId,
            ]);
        }

        return [
            'chat_id' => $chat->getId(),
            'chat_type' => $chatType->value,
            'context_id' => $contextId,
            'message' => array_merge($message->jsonSerialize(), [
                'sender_name' => $sender->getName(),
            ]),
        ];
    }
}
