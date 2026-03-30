<?php

declare(strict_types=1);

namespace App\Domain\Chat\Repositories;

use App\Domain\Chat\ChatMessage;

interface ChatMessageRepositoryInterface
{
    public function save(ChatMessage $message): ChatMessage;

    /**
     * @return ChatMessage[]
     */
    public function findRecent(int $chatId, int $limit = 50): array;

    /**
     * @return ChatMessage[]
     */
    public function findRecentAfter(int $chatId, \DateTimeImmutable $since): array;
}
