<?php

declare(strict_types=1);

namespace App\Domain\Chat\Repositories;

use App\Domain\Chat\Chat;
use App\Domain\Chat\ChatType;

interface ChatRepositoryInterface
{
    public function findByTypeAndContext(ChatType $type, int $contextId): ?Chat;

    public function findPrivateChatBetween(int $firstCharacterId, int $secondCharacterId): ?Chat;

    /**
     * @param int[] $participantIds
     */
    public function create(ChatType $type, ?int $contextId, array $participantIds = []): Chat;

    /**
     * @return int[]
     */
    public function getParticipants(int $chatId): array;

    /**
     * @return Chat[]
     */
    public function listPrivateChatsForCharacter(int $characterId): array;
}
