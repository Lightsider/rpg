<?php

declare(strict_types=1);

namespace App\Domain\Character;

class CurrencyConverter
{
    public static function split(int $copper): array
    {
        $gold = intdiv($copper, 100);
        $remaining = $copper % 100;

        $silver = intdiv($remaining, 10);
        $remainingCopper = $remaining % 10;

        return [
            'gold' => $gold,
            'silver' => $silver,
            'copper' => $remainingCopper,
        ];
    }

    public static function compose(int $gold, int $silver, int $copper): int
    {
        return ($gold * 100) + ($silver * 10) + $copper;
    }
}
