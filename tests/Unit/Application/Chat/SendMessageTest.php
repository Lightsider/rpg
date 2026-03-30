<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Chat;

use App\Application\Chat\SendMessage;
use App\Domain\Chat\Chat;
use App\Domain\Chat\ChatMessage;
use App\Domain\Chat\ChatType;
use App\Domain\Chat\Repositories\ChatMessageRepositoryInterface;
use App\Domain\Chat\Repositories\ChatRepositoryInterface;
use App\Domain\Character\Character;
use App\Domain\Character\Repositories\CharacterRepositoryInterface;
use App\Domain\Battle\Repositories\BattleRepositoryInterface;
use App\Events\Chat\ChatMessageSent;
use DateTimeImmutable;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class SendMessageTest extends TestCase
{
    private ChatRepositoryInterface $chatRepository;
    private ChatMessageRepositoryInterface $chatMessageRepository;
    private CharacterRepositoryInterface $characterRepository;
    private BattleRepositoryInterface $battleRepository;
    private SendMessage $useCase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->chatRepository = $this->createMock(ChatRepositoryInterface::class);
        $this->chatMessageRepository = $this->createMock(ChatMessageRepositoryInterface::class);
        $this->characterRepository = $this->createMock(CharacterRepositoryInterface::class);
        $this->battleRepository = $this->createMock(BattleRepositoryInterface::class);

        $this->useCase = new SendMessage(
            $this->chatRepository,
            $this->chatMessageRepository,
            $this->characterRepository,
            $this->battleRepository
        );
    }

    public function testExecuteSuccessful(): void
    {
        Event::fake();

        $senderId = 1;
        $contextId = 100;
        $chatType = ChatType::LOCATION;
        $messageText = 'Hello world';

        $character = $this->createMock(Character::class);
        $character->method('getId')->willReturn($senderId);
        $character->method('getName')->willReturn('Hero');
        $character->method('getLocationId')->willReturn($contextId);

        $chat = $this->createMock(Chat::class);
        $chat->method('getId')->willReturn(50);
        $chat->method('getType')->willReturn($chatType);
        $chat->method('getContextId')->willReturn($contextId);

        $this->characterRepository->expects($this->once())
            ->method('findById')
            ->with($senderId)
            ->willReturn($character);

        $this->chatRepository->expects($this->once())
            ->method('findByTypeAndContext')
            ->with($chatType, $contextId)
            ->willReturn($chat);

        $this->chatMessageRepository->expects($this->once())
            ->method('save')
            ->willReturnCallback(fn($msg) => $msg);

        $result = $this->useCase->execute($senderId, $chatType, $contextId, $messageText);

        $this->assertEquals('Hero', $result['message']['sender_name']);
        $this->assertEquals($messageText, $result['message']['message']);
        
        Event::assertDispatched(ChatMessageSent::class);
    }
}
