<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Battle\Repositories\BattleRepositoryInterface;
use App\Domain\Character\Repositories\CharacterRepositoryInterface;
use App\Domain\DomainException;
use App\Http\Requests\EquipBackpackItemRequest;
use App\Http\Requests\UnequipBackpackItemRequest;
use App\Infrastructure\Eloquent\Models\CharacterModel;
use App\Services\BackpackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class BackpackController extends Controller
{
    public function __construct(
        private readonly CharacterRepositoryInterface $characterRepository,
        private readonly BattleRepositoryInterface $battleRepository,
        private readonly BackpackService $backpackService
    ) {
    }

    public function equip(EquipBackpackItemRequest $request): JsonResponse
    {
        $user = Auth::user();
        $character = $this->characterRepository->findByUserId($user->id);
        if (!$character) {
            return response()->json(['error' => 'Character not found.'], 404);
        }

        $characterModel = CharacterModel::where('user_id', $user->id)->first();
        if (!$characterModel) {
            return response()->json(['error' => 'Character not found.'], 404);
        }

        $activeBattle = $this->battleRepository->findActiveBattleForCharacter($character->getId());
        if ($activeBattle !== null) {
            return response()->json(['error' => 'Cannot edit loadout during an active fight.'], 400);
        }

        $data = $request->validated();

        try {
            $this->backpackService->equipItem($characterModel, (int) $data['item_id'], (string) $data['slot']);
        } catch (DomainException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        $characterModel->refresh();
        $updatedCharacter = $this->characterRepository->findByUserId($user->id);

        return response()->json([
            'character' => $updatedCharacter,
            'equipment' => $this->backpackService->getEquipmentPayload($characterModel),
            'backpack' => $this->backpackService->getBackpackPayload($characterModel),
        ]);
    }

    public function unequip(UnequipBackpackItemRequest $request): JsonResponse
    {
        $user = Auth::user();
        $character = $this->characterRepository->findByUserId($user->id);
        if (!$character) {
            return response()->json(['error' => 'Character not found.'], 404);
        }

        $characterModel = CharacterModel::where('user_id', $user->id)->first();
        if (!$characterModel) {
            return response()->json(['error' => 'Character not found.'], 404);
        }

        $activeBattle = $this->battleRepository->findActiveBattleForCharacter($character->getId());
        if ($activeBattle !== null) {
            return response()->json(['error' => 'Cannot edit loadout during an active fight.'], 400);
        }

        $data = $request->validated();

        try {
            $this->backpackService->unequipItem($characterModel, (string) $data['slot']);
        } catch (DomainException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        $characterModel->refresh();
        $updatedCharacter = $this->characterRepository->findByUserId($user->id);

        return response()->json([
            'character' => $updatedCharacter,
            'equipment' => $this->backpackService->getEquipmentPayload($characterModel),
            'backpack' => $this->backpackService->getBackpackPayload($characterModel),
        ]);
    }
}
