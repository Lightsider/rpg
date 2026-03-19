<?php

namespace App\Http\Controllers;

use App\Domain\Battle\BattleState;
use App\Domain\Battle\Repositories\BattleRepositoryInterface;
use App\Domain\Character\Character;
use App\Domain\Character\Repositories\CharacterRepositoryInterface;
use App\Domain\DomainException;
use App\Domain\Equipment\Equipment;
use App\Domain\Location\Repositories\LocationRepositoryInterface;
use App\Services\CharacterStatService;
use App\Services\CharacterStatValidator;
use App\Services\WeaponAssigner;
use App\Infrastructure\Eloquent\Models\CharacterModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GameController extends Controller
{
    public function __construct(
        private readonly CharacterRepositoryInterface $characterRepository,
        private readonly LocationRepositoryInterface $locationRepository,
        private readonly BattleRepositoryInterface $battleRepository,
        private readonly WeaponAssigner $weaponAssigner,
        private readonly CharacterStatValidator $statValidator,
        private readonly CharacterStatService $statService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();

        // Load or create character
        $character = $this->characterRepository->findByUserId($user->id);
        
        if (!$character) {
            $defaultStats = [
                'str' => 5,
                'con' => 5,
                'dex' => 5,
                'wit' => 5,
            ];

            try {
                $this->statValidator->validateStats($defaultStats);
            } catch (DomainException $e) {
                return response()->json(['error' => $e->getMessage()], 422);
            }

            $computedHp = $this->statService->calculateHp($defaultStats['con']);

            // Create default character for new user
            $newCharacter = new Character(
                id: 0, // Auto-generated
                userId: $user->id,
                name: $user->name,
                strength: $defaultStats['str'],
                agility: $defaultStats['dex'],
                constitution: $defaultStats['con'],
                wit: $defaultStats['wit'],
                maxHp: $computedHp,
                currentHp: $computedHp,
                equipment: new Equipment(),
                locationId: 1 // Training Grounds
            );
            $character = $this->characterRepository->create($newCharacter);
        }

        $characterModel = CharacterModel::where('user_id', $user->id)->first();
        if ($characterModel) {
            $this->weaponAssigner->ensureCharacterHasWeapon($characterModel);
            $character = $this->characterRepository->findByUserId($user->id) ?? $character;
        }

        // Load current location
        $location = $this->locationRepository->findById($character->getLocationId());

        $currentFight = $this->battleRepository->findActiveBattleForCharacter($character->getId());
        $isWaiting = $currentFight && $currentFight->getState() === BattleState::WAITING;

        // Load available fights in the location
        $availableFights = $this->battleRepository->findActiveByLocation($character->getLocationId());

        return response()->json([
            'character' => $character,
            'location' => $location,
            'availableFights' => $availableFights,
            'currentFight' => $currentFight,
            'canCreateFight' => !$isWaiting,
            'canLeaveLocation' => !$isWaiting,
        ]);
    }
}
