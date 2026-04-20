<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\Contracts\TransactionInterface;
use Illuminate\Support\Facades\DB;

class LaravelTransactionManager implements TransactionInterface
{
    public function run(callable $callback): mixed
    {
        return DB::transaction($callback);
    }
}

