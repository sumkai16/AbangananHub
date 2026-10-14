<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTenantConcernRequest;
use App\Models\Conversation;
use App\Models\Notification;
use App\Models\TenantConcern;
use App\Services\ImageUploader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class TenantConcernController extends Controller
{
    public function store(StoreTenantConcernRequest $request, Conversation $conversation, ImageUploader $uploader): JsonResponse
    {
        $photos = collect($request->file('photos', []))
            ->map(fn ($file) => $uploader->upload($file, 'abanganan/concerns'))
            ->values()
            ->all();

        [$concern, $message] = DB::transaction(function () use ($request, $conversation, $photos) {
            $concern = TenantConcern::create([
                'conversation_id' => $conversation->conversation_id,
                'reservation_id' => $conversation->reservation_id,
                'tenant_id' => Auth::id(),
                'category' => $request->validated('category'),
                'description' => $request->validated('description'),
                'photos' => $photos ?: null,
            ]);

            $message = $conversation->messages()->create([
                'sender_id' => Auth::id(),
                'message' => $concern->description,
                'concern_id' => $concern->concern_id,
            ]);

            return [$concern, $message];
        });

        $conversation->broadcastQuietly($message->load('sender'));

        Notification::notify(
            $conversation->landlord_id,
            'concern',
            'New concern: '.$concern->category,
            Auth::user()->first_name.' reported a concern. '.str($concern->description)->limit(80),
            route('conversations.index', ['box' => 'tenancy', 'active' => $conversation->conversation_id]),
            $conversation->conversation_id,
        );

        return response()->json(['concern_id' => $concern->concern_id], 201);
    }

    public function updateStatus(Request $request, TenantConcern $concern): JsonResponse
    {
        $to = $request->validate(['status' => ['required', Rule::in(TenantConcern::STATUSES)]])['status'];

        Gate::authorize('updateStatus', [$concern, $to]);

        $concern->markStatus($to, $request->user());

        return response()->json(['status' => $concern->status]);
    }
}
