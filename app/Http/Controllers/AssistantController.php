<?php

namespace App\Http\Controllers;

use App\Models\AssistantMessage;
use App\Services\AssistantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssistantController extends Controller
{
    public function store(Request $request, AssistantService $service): JsonResponse
    {
        $data = $request->validate(['message' => ['required', 'string', 'max:1000']]);
        $user = $request->user();
        $context = $service->contextFor($user);
        $storedContext = $context === [] ? null : json_encode($context, JSON_UNESCAPED_UNICODE);

        AssistantMessage::create([
            'user_id' => $user?->id,
            'role' => 'user',
            'message' => $data['message'],
            'context' => $storedContext,
        ]);

        $reply = $service->respond($data['message'], $context);

        AssistantMessage::create([
            'user_id' => $user?->id,
            'role' => 'assistant',
            'message' => $reply,
            'context' => $storedContext,
        ]);

        return response()->json(['reply' => $reply]);
    }
}
