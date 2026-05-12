<?php

declare(strict_types=1);

namespace App\Infrastructure\Errors;

class DomainExceptionHttpMapper
{
    public static function toStatusCode(string $message): int
    {
        $normalized = rtrim(trim($message), '.');

        return match ($normalized) {
            'Character not found' => 404,
            'Location not found' => 404,
            'Fight not found' => 404,
            'Battle not found' => 404,
            'Target character not found' => 404,
            'Store not found' => 404,
            'Store item not found' => 404,
            'Item not found' => 404,
            'Cannot edit loadout during an active fight' => 400,
            'You cannot change locations while in a fight' => 409,
            'Fight is no longer joinable' => 409,
            'Fight is no longer waiting' => 409,
            'Fight is no longer in lobby phase' => 409,
            'Fight expired' => 409,
            'Round has expired' => 409,
            'You are not a participant in this ongoing fight' => 403,
            'You are not a participant in this fight' => 403,
            'You are not a participant in this battle' => 403,
            'Cannot use more off-hand attacks than available bonus AP' => 400,
            'Maximum 2 main-hand attacks per round' => 400,
            'Not enough Action Points for these actions' => 400,
            'Extra action from shield can only be used for defense' => 400,
            'Attack action is missing target zone' => 400,
            'Offhand attack action is missing target zone' => 400,
            'Block action is missing target zone' => 400,
            'Move action is missing target coordinates' => 400,
            default => 422,
        };
    }
}
