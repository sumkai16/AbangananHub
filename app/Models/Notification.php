<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $fillable = [
        'user_id',
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

    // ─── Relationships ───────────────────────────────────────

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    // ─── Helpers ─────────────────────────────────────────────

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