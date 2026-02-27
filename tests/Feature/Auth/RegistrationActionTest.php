<?php

namespace Tests\Feature\Auth;

use App\DTOs\UserRegistrationDTO;
use App\Models\User;
use App\Services\Auth\RegisterUserAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RegistrationActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_be_registered_via_action(): void
    {
        Event::fake();

        $action = $this->app->make(RegisterUserAction::class);

        $dto = new UserRegistrationDTO(
            name: 'Arthas Menethil',
            email: 'arthas@aethelia.com',
            password: 'frostmournehungers'
        );

        $user = $action->execute($dto);

        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals('Arthas Menethil', $user->name);
        $this->assertEquals('arthas@aethelia.com', $user->email);
        $this->assertTrue(Hash::check('frostmournehungers', $user->password));

        $this->assertDatabaseHas('users', [
            'email' => 'arthas@aethelia.com',
        ]);

        Event::assertDispatched(Registered::class);
    }
}
