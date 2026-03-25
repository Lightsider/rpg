<?php

declare(strict_types=1);

namespace App\Application\Character;

use App\Domain\Character\Repositories\CharacterRepositoryInterface;
use App\Events\CharacterCurrencyUpdated;
use App\Domain\DomainException;

class CurrencyService
{
    public function __construct(
        private readonly CharacterRepositoryInterface $characterRepository
    ) {
    }

    public function getBalance(int $characterId): int
    {
        $character = $this->characterRepository->findById($characterId);
        if (!$character) {
            throw new DomainException("Character (ID: {$characterId}) not found.");
        }

        return $character->getCurrencyCopper();
    }

    public function addCurrency(int $characterId, int $amount): int
    {
        if ($amount < 0) {
            throw new DomainException('Cannot add negative currency.');
        }

        $character = $this->characterRepository->findById($characterId);
        if (!$character) {
            throw new DomainException("Character (ID: {$characterId}) not found.");
        }

        $character->addCurrency($amount);
        $newBalance = $character->getCurrencyCopper();

        $this->characterRepository->updateCurrency($characterId, $newBalance);

        broadcast(new CharacterCurrencyUpdated($characterId, $newBalance));

        return $newBalance;
    }

    public function spendCurrency(int $characterId, int $amount): array
    {
        if ($amount < 0) {
            throw new DomainException('Cannot spend negative currency.');
        }

        $character = $this->characterRepository->findById($characterId);
        if (!$character) {
            throw new DomainException("Character (ID: {$characterId}) not found.");
        }

        $success = $character->spendCurrency($amount);
        $newBalance = $character->getCurrencyCopper();

        if ($success) {
            $this->characterRepository->updateCurrency($characterId, $newBalance);
            broadcast(new CharacterCurrencyUpdated($characterId, $newBalance));
        }

        return [
            'success' => $success,
            'newBalance' => $newBalance,
        ];
    }
}
