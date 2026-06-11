<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FriendRequestStatus;
use Database\Factories\FriendRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['sender_id', 'receiver_id', 'status', 'responded_at'])]
class FriendRequest extends Model
{
    /** @use HasFactory<FriendRequestFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => FriendRequestStatus::class,
            'responded_at' => 'datetime',
        ];
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', FriendRequestStatus::Pending);
    }

    public function isPending(): bool
    {
        return $this->status === FriendRequestStatus::Pending;
    }
}
