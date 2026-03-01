<?php

namespace Tests\Feature\Auth;

use App\DTOs\UserRegistrationDTO;
use App\Infrastructure\Eloquent\Models\User;
use App\Application\User\Actions\RegisterUserAction;
use App\Domain\User\Entities\UserEntity;
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

        $userEntity = $action->execute($dto);

        $this->assertInstanceOf(UserEntity::class, $userEntity);
        $this->assertEquals('Arthas Menethil', $userEntity->name);
        $this->assertEquals('arthas@aethelia.com', $userEntity->email);
        $this->assertTrue(Hash::check('frostmournehungers', $userEntity->password));

        $this->assertDatabaseHas('users', [
            'email' => 'arthas@aethelia.com',
        ]);

        Event::assertDispatched(Registered::class);
    }
}
