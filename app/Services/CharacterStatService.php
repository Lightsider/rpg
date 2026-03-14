<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\DomainException;

class CharacterStatService
{
    public function calculateHp(int $con): int
    {
        $baseHp = config('game.base_hp');
        $hpPerCon = config('game.hp_per_con');

        if (!is_numeric($baseHp) || !is_numeric($hpPerCon)) {
            throw new DomainException('HP configuration is missing or invalid.');
        }

        return (int) ceil($baseHp + ($con * $hpPerCon));
    }
}
