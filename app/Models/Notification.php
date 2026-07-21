<?php

namespace App\Models;

use App\Events\NotificationCreated;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $primaryKey = 'notification_id';

    protected $fillable = [
        'user_id',
        'type',
        'notifiable_type',
        'notifiable_id',
        'conversation_id',
        'title',
        'message',
        'link',
        'is_read',
    ];

    protected function casts(): array
    {
        return [
            'is_read'    => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function conversation()
    {
        return $this->belongsTo(Conversation::class, 'conversation_id', 'conversation_id');
    }

    public function notifiable()
    {
        return $this->morphTo('notifiable', 'notifiable_type', 'notifiable_id');
    }

    /**
     * The single place a notification is created.
     *
     * Every caller goes through here so the broadcast can never be forgotten —
     * the same failure that left postSystemMessage() writing rows nobody saw
     * until a reload. Mirrors that method's shape deliberately.
     *
     * Returns null when there is no recipient, so callers can hook it up
     * without null-checking the actor first.
     */
    public static function notify(
        ?int $userId,
        string $type,
        string $title,
        string $message,
        ?string $link = null,
        ?int $conversationId = null,
    ): ?self {
        if (! $userId) {
            return null;
        }

        $notification = static::create([
            'user_id'         => $userId,
            'type'            => $type,
            'title'           => $title,
            'message'         => $message,
            'link'            => $link,
            'conversation_id' => $conversationId,
            'is_read'         => false,
        ]);

        NotificationCreated::dispatch($notification);

        return $notification;
    }

    public function markAsRead(): bool
    {
        if ($this->is_read) {
            return true;
        }

        $this->is_read = true;
        return $this->save();
    }

    public static function markAllAsRead(int $userId): void
    {
        static::where('user_id', $userId)
            ->where('is_read', false)
            ->update(['is_read' => true]);
    }

    public static function unreadCount(int $userId): int
    {
        return static::where('user_id', $userId)
            ->where('is_read', false)
            ->count();
    }
}