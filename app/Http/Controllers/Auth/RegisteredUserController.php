<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Infrastructure\Eloquent\Models\User;
use App\Application\User\Actions\RegisterUserAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request, RegisterUserAction $registerUserAction): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:' . User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $dto = \App\DTOs\UserRegistrationDTO::fromArray($request->only(['name', 'email', 'password']));

        $userEntity = $registerUserAction->execute($dto);

        // For Laravel's Auth facade, we need the Eloquent model
        $user = User::findOrFail($userEntity->id);

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }
}
