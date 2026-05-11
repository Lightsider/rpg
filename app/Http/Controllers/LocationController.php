<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Location\EnterLocation;
use App\Application\Location\ListLocations;
use App\Application\Location\ShowLocation;
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
        return response()->json($this->showLocation->execute($id));
    }

    public function enter(int $id): JsonResponse
    {
        $user = Auth::user();
        return response()->json($this->enterLocation->execute($user->id, $id));
    }
}
