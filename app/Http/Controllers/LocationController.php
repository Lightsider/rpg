<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Location\EnterLocation;
use App\Application\Location\ListLocations;
use App\Application\Location\ShowLocation;
use App\Domain\DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class LocationController extends Controller
{
    public function __construct(
        private readonly ListLocations $listLocations,
        private readonly ShowLocation $showLocation,
        private readonly EnterLocation $enterLocation
    ) {
    }

    public function index(): JsonResponse
    {
        return response()->json($this->listLocations->execute());
    }

    public function show(int $id): JsonResponse
    {
        try {
            return response()->json($this->showLocation->execute($id));
        } catch (DomainException $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        }
    }

    public function enter(int $id): JsonResponse
    {
        $user = Auth::user();
        try {
            return response()->json($this->enterLocation->execute($user->id, $id));
        } catch (DomainException $e) {
            $status = $e->getMessage() === 'You cannot change locations while in a fight.' ? 409 : 404;
            return response()->json(['error' => $e->getMessage()], $status);
        }
    }
}
