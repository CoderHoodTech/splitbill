<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SplitMethod;
use Database\Factories\ExpenseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'group_id',
    'payer_id',
    'description',
    'total_amount',
    'split_method',
    'expense_date',
])]
class Expense extends Model
{
    /** @use HasFactory<ExpenseFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'total_amount' => 'integer',
            'split_method' => SplitMethod::class,
            'expense_date' => 'date',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payer_id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(ExpenseParticipant::class);
    }

    public function isEditableBy(User $user): bool
    {
        return (int) $this->payer_id === (int) $user->getKey()
            || $this->group->isOwnedBy($user);
    }
}
