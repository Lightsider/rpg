<?php

namespace App\Http\Controllers;

use App\Domain\Character\Character;
use App\Domain\Character\Repositories\CharacterRepositoryInterface;
use App\Domain\Location\Repositories\LocationRepositoryInterface;
use App\Domain\Battle\Repositories\BattleRepositoryInterface;
use App\Domain\Equipment\Equipment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GameController extends Controller
{
    public function __construct(
        private readonly CharacterRepositoryInterface $characterRepository,
        private readonly LocationRepositoryInterface $locationRepository,
        private readonly BattleRepositoryInterface $battleRepository
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();

        // Load or create character
        $character = $this->characterRepository->findByUserId($user->id);

        if (!$character) {
            // Create default character for new user
            $newCharacter = new Character(
                id: 0, // Auto-generated
                userId: $user->id,
                name: $user->name,
                strength: 10,
                agility: 10,
                constitution: 10,
                wit: 10,
                maxHp: 100,
                currentHp: 100,
                equipment: new Equipment(),
                locationId: 1 // Training Grounds
            );
            $character = $this->characterRepository->create($newCharacter);
        }

        // Load current location
        $location = $this->locationRepository->findById($character->getLocationId());

        // Load available fights in the location
        $availableFights = $this->battleRepository->findActiveByLocation($character->getLocationId());

        return response()->json([
            'character' => $character,
            'location' => $location,
            'availableFights' => $availableFights
        ]);
    }
}
