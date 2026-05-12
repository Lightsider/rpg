<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Chat\GetChatStateForUser;
use App\Application\Chat\GetParticipants;
use App\Application\Chat\GetPrivateChatsForUser;
use App\Application\Chat\SendChatMessageForUser;
use App\Domain\Chat\ChatType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ChatController extends Controller
{
    public function __construct(
        private readonly SendChatMessageForUser $sendChatMessageForUser,
        private readonly GetChatStateForUser $getChatStateForUser,
        private readonly GetParticipants $getParticipants,
        private readonly GetPrivateChatsForUser $getPrivateChatsForUser
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

        $result = $this->sendChatMessageForUser->execute(
            $user->id,
            ChatType::from($data['chatType']),
            (int) $data['contextId'],
            (string) $data['message']
        );
        return response()->json($result);
    }

    public function state(Request $request): JsonResponse
    {
        $data = $request->validate([
            'chatType' => ['required', 'string', 'in:location,battle,private'],
            'contextId' => ['required', 'integer'],
        ]);

        $user = Auth::user();

        $state = $this->getChatStateForUser->execute(
            $user->id,
            ChatType::from($data['chatType']),
            (int) $data['contextId']
        );
        return response()->json($state);
    }

    public function privateChats(): JsonResponse
    {
        $user = Auth::user();

        $chats = $this->getPrivateChatsForUser->execute($user->id);
        return response()->json($chats);
    }

    public function participants(Request $request): JsonResponse
    {
        $data = $request->validate([
            'chatType' => ['required', 'string', 'in:location,battle,private'],
            'contextId' => ['required', 'integer'],
        ]);

        $participants = $this->getParticipants->execute(
            ChatType::from($data['chatType']),
            (int) $data['contextId']
        );
        return response()->json($participants);
    }
}
