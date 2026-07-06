<?php

namespace App\Models;

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