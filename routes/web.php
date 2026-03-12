<?php

use App\Http\Controllers\BattleController;
use App\Http\Controllers\GameController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\FightController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\CharacterLoadoutController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware(['auth'])->group(function () {
    // Vue Inertia Shell Routes
    Route::get('/game', function () {
        return Inertia::render('GameView');
    })->name('game.index');

    Route::get('/fight/{id}', function ($id) {
        return Inertia::render('FightView', ['fightId' => (int) $id]);
    })->name('fight.view');

    // JSON API Endpoints
    Route::prefix('api')->group(function () {
        Route::get('/game', [GameController::class, 'index'])->name('api.game.index');
        Route::get('/character/loadout', [CharacterLoadoutController::class, 'loadout'])->name('api.character.loadout');
        Route::put('/character/loadout', [CharacterLoadoutController::class, 'update'])->name('api.character.update_loadout');
        Route::get('/weapons', [CharacterLoadoutController::class, 'weapons'])->name('api.weapons.index');
        Route::get('/locations', [LocationController::class, 'index'])->name('api.locations.index');
        Route::get('/locations/{id}', [LocationController::class, 'show'])->name('api.locations.show');

        Route::get('/fights', [FightController::class, 'index'])->name('api.fights.index');
        Route::post('/fights', [FightController::class, 'create'])->name('api.fights.create');
        Route::get('/fights/{id}', [FightController::class, 'show'])->name('api.fights.show');
        Route::post('/fights/{id}/join', [FightController::class, 'join'])->name('api.fights.join');
        Route::post('/fights/{id}/cancel', [FightController::class, 'cancel'])->name('api.fights.cancel');
        Route::post('/fights/{id}/actions', [FightController::class, 'submitActions'])->name('api.fights.submit_actions');
        Route::get('/fights/{id}/log', [FightController::class, 'log'])->name('api.fights.log');
    });
});

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::get('/ping', fn() => 'pong');

require __DIR__ . '/auth.php';



