<?php

declare(strict_types=1);

namespace App\Domain\Contracts;

interface LoggerInterface
{
    /**
     * @param array<string, mixed> $context
     */
    public function debug(string $message, array $context = []): void;
}
