<?php

declare(strict_types=1);

namespace App\Domain\Chat;

class Chat implements \JsonSerializable
{
    /**
     * @param int[] $participants
     */
    public function __construct(
        private readonly int $id,
        private readonly ChatType $type,
        private readonly ?int $contextId,
        private readonly array $participants = []
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getType(): ChatType
    {
        return $this->type;
    }

    public function getContextId(): ?int
    {
        return $this->contextId;
    }

    /**
     * @return int[]
     */
    public function getParticipants(): array
    {
        return $this->participants;
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'context_id' => $this->contextId,
            'participants' => $this->participants,
        ];
    }
}
