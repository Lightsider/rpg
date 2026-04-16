<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Battle\Repositories\BattleRepositoryInterface;
use App\Domain\Character\Repositories\CharacterRepositoryInterface;
use App\Domain\DomainException;
use App\Http\Requests\UpdateCharacterLoadoutRequest;
use App\Infrastructure\Eloquent\Models\CharacterModel;
use App\Services\BackpackService;
use App\Services\CharacterStatService;
use App\Services\CharacterStatValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class CharacterLoadoutController extends Controller
{
    public function __construct(
        private readonly CharacterRepositoryInterface $characterRepository,
        private readonly BattleRepositoryInterface $battleRepository,
        private readonly CharacterStatValidator $statValidator,
        private readonly CharacterStatService $statService,
        private readonly BackpackService $backpackService
    ) {
    }

    public function loadout(): JsonResponse
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
        $canEdit = $activeBattle === null;

        return response()->json([
            'stats' => [
                'strength' => (int) $characterModel->strength,
                'dexterity' => (int) $characterModel->dexterity,
                'constitution' => (int) $characterModel->constitution,
                'wit' => (int) $characterModel->wit,
            ],
            'level' => (int) ($characterModel->level ?? 1),
            'experience' => (int) ($characterModel->experience ?? 0),
            'equipment' => $this->backpackService->getEquipmentPayload($characterModel),
            'backpack' => $this->backpackService->getBackpackPayload($characterModel),
            'can_edit' => $canEdit,
            'blocked_reason' => $canEdit ? null : 'Cannot edit loadout while you are in a fight.',
        ]);
    }

    public function update(UpdateCharacterLoadoutRequest $request): JsonResponse
    {
        $user = Auth::user();
        $character = $this->characterRepository->findByUserId($user->id);
        if (!$character) {
            return response()->json(['error' => 'Character not found.'], 404);
        }

        $activeBattle = $this->battleRepository->findActiveBattleForCharacter($character->getId());
        if ($activeBattle !== null) {
            return response()->json(['error' => 'Cannot edit loadout during an active fight.'], 400);
        }

        $data = $request->validated();

        $stats = [
            'str' => (int) $data['strength'],
            'con' => (int) $data['constitution'],
            'dex' => (int) $data['dexterity'],
            'wit' => (int) $data['wit'],
        ];

        try {
            $this->statValidator->validateStats($stats);
        } catch (DomainException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        $computedHp = $this->statService->calculateHp($stats['con']);

        $updates = [
            'hp' => $computedHp,
            'max_hp' => $computedHp,
        ];

        foreach (['strength', 'dexterity', 'constitution', 'wit'] as $field) {
            if (array_key_exists($field, $data)) {
                $updates[$field] = (int) $data[$field];
            }
        }

        CharacterModel::where('user_id', $user->id)
            ->update($updates);

        $characterModel = CharacterModel::where('user_id', $user->id)->first();
        if (!$characterModel) {
             return response()->json(['error' => 'Character not found.'], 404);
        }

        $unequipped = $this->backpackService->validateEquippedItems($characterModel);

        $updatedCharacter = $this->characterRepository->findByUserId($user->id);

        return response()->json([
            'character' => $updatedCharacter,
            'stats' => [
                'strength' => (int) ($characterModel->strength ?? 4),
                'dexterity' => (int) ($characterModel->dexterity ?? 4),
                'constitution' => (int) ($characterModel->constitution ?? 4),
                'wit' => (int) ($characterModel->wit ?? 4),
            ],
            'unequipped' => $unequipped,
        ]);
    }
}
