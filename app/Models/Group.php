<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\GroupMemberRole;
use Database\Factories\GroupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'description', 'owner_id'])]
class Group extends Model
{
    /** @use HasFactory<GroupFactory> */
    use HasFactory;

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'group_members')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function groupMembers(): HasMany
    {
        return $this->hasMany(GroupMember::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function settlements(): HasMany
    {
        return $this->hasMany(Settlement::class);
    }

    public function hasMember(User $user): bool
    {
        return $this->groupMembers()->where('user_id', $user->getKey())->exists();
    }

    public function isOwnedBy(User $user): bool
    {
        return (int) $this->owner_id === (int) $user->getKey();
    }

    public function roleFor(User $user): ?GroupMemberRole
    {
        $member = $this->groupMembers()->where('user_id', $user->getKey())->first();

        return $member?->role;
    }
}
