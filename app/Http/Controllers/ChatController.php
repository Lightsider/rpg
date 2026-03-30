<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Chat\GetChatState;
use App\Application\Chat\GetParticipants;
use App\Application\Chat\GetPrivateChats;
use App\Application\Chat\SendMessage;
use App\Domain\Chat\ChatType;
use App\Domain\Character\Repositories\CharacterRepositoryInterface;
use App\Domain\DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ChatController extends Controller
{
    public function __construct(
        private readonly SendMessage $sendMessage,
        private readonly GetChatState $getChatState,
        private readonly GetParticipants $getParticipants,
        private readonly GetPrivateChats $getPrivateChats,
        private readonly CharacterRepositoryInterface $characterRepository
    ) {
    }

    public function send(Request $request): JsonResponse
    {
        $data = $request->validate([
            'chatType' => ['required', 'string', 'in:location,battle,private'],
            'contextId' => ['required', 'integer'],
            'message' => ['required', 'string', 'max:500'],
        ]);

        $user = Auth::user();
        $character = $this->characterRepository->findByUserId($user->id);
        if (!$character) {
            return response()->json(['error' => 'Character not found.'], 404);
        }

        try {
            $result = $this->sendMessage->execute(
                $character->getId(),
                ChatType::from($data['chatType']),
                (int) $data['contextId'],
                (string) $data['message']
            );
            return response()->json($result);
        } catch (DomainException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function state(Request $request): JsonResponse
    {
        $data = $request->validate([
            'chatType' => ['required', 'string', 'in:location,battle,private'],
            'contextId' => ['required', 'integer'],
        ]);

        $user = Auth::user();
        $character = $this->characterRepository->findByUserId($user->id);
        if (!$character) {
            return response()->json(['error' => 'Character not found.'], 404);
        }

        try {
            $state = $this->getChatState->execute(
                $character->getId(),
                ChatType::from($data['chatType']),
                (int) $data['contextId']
            );
            return response()->json($state);
        } catch (DomainException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function privateChats(): JsonResponse
    {
        $user = Auth::user();
        $character = $this->characterRepository->findByUserId($user->id);
        if (!$character) {
            return response()->json(['error' => 'Character not found.'], 404);
        }

        try {
            $chats = $this->getPrivateChats->execute($character->getId());
            return response()->json($chats);
        } catch (DomainException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function participants(Request $request): JsonResponse
    {
        $data = $request->validate([
            'chatType' => ['required', 'string', 'in:location,battle,private'],
            'contextId' => ['required', 'integer'],
        ]);

        try {
            $participants = $this->getParticipants->execute(
                ChatType::from($data['chatType']),
                (int) $data['contextId']
            );
            return response()->json($participants);
        } catch (DomainException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}
