<?php
namespace App\Policies;
use App\Models\Conversation;
use App\Models\User;
class ConversationPolicy
{
    public function view(User $user, Conversation $conversation): bool
    {
        return $user->user_id === $conversation->tenant_id
            || $user->user_id === $conversation->landlord_id;
    }

    public function resolve(User $user, Conversation $conversation): bool
    {
        return $user->user_id === $conversation->landlord_id
            || $user->hasRole('Admin');
    }
}