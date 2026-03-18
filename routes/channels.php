<?php

use App\Domain\Battle\Repositories\BattleRepositoryInterface;
use App\Domain\Character\Repositories\CharacterRepositoryInterface;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

/**
 * Private per-battle channel.
 * Only participants of the battle may subscribe.
 * This prevents other users from eavesdropping on battle events.
 */
Broadcast::channel('battle.{battleId}', function ($user, int $battleId) {
    /** @var BattleRepositoryInterface $battleRepository */
    $battleRepository = app(BattleRepositoryInterface::class);

    /** @var CharacterRepositoryInterface $characterRepository */
    $characterRepository = app(CharacterRepositoryInterface::class);

    $battle = $battleRepository->findById($battleId);
    if ($battle === null) {
        return false;
    }

    $character = $characterRepository->findByUserId($user->id);
    if ($character === null) {
        return false;
    }

    return $battle->getParticipantById($character->getId()) !== null;
});

/**
 * Private per-character channel.
 * Only the owner of the character may subscribe.
 */
Broadcast::channel('character.{characterId}', function ($user, int $characterId) {
    /** @var CharacterRepositoryInterface $characterRepository */
    $characterRepository = app(CharacterRepositoryInterface::class);

    $character = $characterRepository->findByUserId($characterId);
    if ($character === null) {
        return false;
    }

    return (int) $user->id === (int) $character->getUserId();
});

Broadcast::channel('location.{locationId}', function ($user, int $locationId) {
    return (bool) $user;
});
