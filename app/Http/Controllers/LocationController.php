<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Location\Repositories\LocationRepositoryInterface;
use App\Domain\Battle\Repositories\BattleRepositoryInterface;
use App\Infrastructure\Eloquent\Models\CharacterModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class LocationController extends Controller
{
    public function __construct(
        private readonly LocationRepositoryInterface $locationRepository,
        private readonly BattleRepositoryInterface $battleRepository
    ) {
    }

    public function index(): JsonResponse
    {
        $locations = $this->locationRepository->findAll();

        return response()->json($locations);
    }

    public function show(int $id): JsonResponse
    {
        $location = $this->locationRepository->findById($id);

        if (!$location) {
            return response()->json(['error' => 'Location not found'], 404);
        }

        $activeFights = $this->battleRepository->findActiveByLocation($id);

        return response()->json([
            'id' => $location->getId(),
            'name' => $location->getName(),
            'description' => $location->getDescription(),
            'active_fights' => $activeFights,
        ]);
    }

    public function enter(int $id): JsonResponse
    {
        $user = Auth::user();
        $character = CharacterModel::where('user_id', $user->id)->first();

        if (!$character) {
            return response()->json(['error' => 'Character not found.'], 404);
        }

        if ($this->battleRepository->isCharacterInBattle($user->id)) {
            return response()->json(['error' => 'You cannot change locations while in a fight.'], 409);
        }

        $location = $this->locationRepository->findById($id);

        if (!$location) {
            return response()->json(['error' => 'Location not found'], 404);
        }

        if ((int) $character->location_id !== $location->getId()) {
            $character->update(['location_id' => $location->getId()]);
        }

        return response()->json([
            'location' => $location,
        ]);
    }
}
