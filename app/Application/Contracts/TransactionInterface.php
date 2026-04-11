<?php

declare(strict_types=1);

namespace App\Application\Contracts;

interface TransactionInterface
{
    /**
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public function run(callable $callback): mixed;
}
