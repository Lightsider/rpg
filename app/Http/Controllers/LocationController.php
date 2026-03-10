<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Location\Repositories\LocationRepositoryInterface;
use App\Domain\Battle\Repositories\BattleRepositoryInterface;
use Illuminate\Http\JsonResponse;

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
}
