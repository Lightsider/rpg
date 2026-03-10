<?php

use App\Http\Controllers\BattleController;
use App\Http\Controllers\GameController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\FightController;
use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::post('/attack', [BattleController::class, 'attack']);

Route::middleware(['auth'])->group(function () {
    Route::get('/game', [GameController::class, 'index'])->name('game.index');
    Route::get('/locations', [LocationController::class, 'index'])->name('locations.index');
    Route::get('/locations/{id}', [LocationController::class, 'show'])->name('locations.show');

    Route::get('/fights', [FightController::class, 'index'])->name('fights.index');
    Route::post('/fights', [FightController::class, 'create'])->name('fights.create');
    Route::get('/fights/{id}', [FightController::class, 'show'])->name('fights.show');
    Route::post('/fights/{id}/join', [FightController::class, 'join'])->name('fights.join');
    Route::post('/fights/{id}/actions', [FightController::class, 'submitActions'])->name('fights.submit_actions');
    Route::get('/fights/{id}/log', [FightController::class, 'log'])->name('fights.log');
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

require __DIR__ . '/auth.php';
