<?php

declare(strict_types=1);

namespace App\Livewire\Expenses;

use App\Actions\Expenses\AddExpense as AddExpenseAction;
use App\Actions\Expenses\ScanReceipt;
use App\Enums\SplitMethod;
use App\Exceptions\ExpenseException;
use App\Models\Group;
use App\Models\User;
use Flux\Flux;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class AddExpense extends Component
{
    use WithFileUploads;

    public Group $group;

    #[Validate('required|string|min:2|max:255')]
    public string $description = '';

    #[Validate('required|integer|min:1|max:9999999999')]
    public int $total_amount = 0;

    #[Validate('required|integer|exists:users,id')]
    public int $payer_id = 0;

    #[Validate('required|date')]
    public string $expense_date = '';

    #[Validate('required|in:equal,exact,percent')]
    public string $split_method = 'equal';

    /**
     * @var array<int, int> user_id => 1 (selected) ; key is user id
     */
    public array $participants = [];

    /**
     * @var array<int, string> user_id => share input (string to keep Livewire happy with empty fields)
     */
    public array $shareInputs = [];

    /**
     * Temporary receipt image. Lives in Livewire's tmp dir only — we forward
     * the bytes to the OCR service and then discard the upload (rule 03).
     */
    #[Validate(['nullable', 'image', 'mimes:jpg,jpeg,png,webp,heic', 'max:5120'])]
    public mixed $receipt = null;

    public bool $hasOcrResult = false;

    public ?string $ocrError = null;

    public ?string $ocrEngine = null;

    public ?int $ocrSuggestedTotal = null;

    /**
     * @var list<array{name: string, amount: int}>
     */
    public array $ocrLineItems = [];

    public function mount(Group $group): void
    {
        if (! Auth::user()->can('addExpense', $group)) {
            throw new AuthorizationException;
        }

        $this->group = $group;
        $this->payer_id = (int) Auth::id();
        $this->expense_date = now()->toDateString();

        foreach ($this->groupMemberIds() as $id) {
            $this->participants[$id] = 1;
            $this->shareInputs[$id] = '';
        }
    }

    /**
     * @return Collection<int, User>
     */
    #[Computed]
    public function members(): Collection
    {
        return $this->group->members()->orderBy('name')->get();
    }

    /**
     * Triggered automatically by Livewire when the file finishes uploading.
     * Forwards the image to the OCR service, prefills total, and discards
     * the temp upload so nothing lingers on disk (rule 03).
     */
    public function updatedReceipt(): void
    {
        $this->resetValidation('receipt');
        $this->validateOnly('receipt');

        if (! $this->receipt instanceof TemporaryUploadedFile) {
            return;
        }

        $result = app(ScanReceipt::class)->execute($this->receipt);

        $this->hasOcrResult = true;
        $this->ocrEngine = $result['engine'];
        $this->ocrSuggestedTotal = $result['total_guess'];
        $this->ocrLineItems = $result['line_items'];
        $this->ocrError = $result['available'] ? null : $result['error'];

        if ($result['available'] && $result['total_guess'] !== null) {
            $this->total_amount = $result['total_guess'];
        }

        // Best-effort: prefill description from the first line item when
        // none has been typed yet. User can always overwrite before saving.
        if ($this->description === '' && $this->ocrLineItems !== []) {
            $this->description = $this->ocrLineItems[0]['name'];
        }

        $this->discardReceipt();

        if (! $result['available']) {
            Flux::toast(
                variant: 'warning',
                heading: __('OCR unavailable'),
                text: __('Receipt could not be scanned. Enter the amount manually.'),
            );
        }
    }

    public function dismissOcr(): void
    {
        $this->hasOcrResult = false;
        $this->ocrEngine = null;
        $this->ocrSuggestedTotal = null;
        $this->ocrLineItems = [];
        $this->ocrError = null;
    }

    public function save(AddExpenseAction $action): void
    {
        $this->validate();

        $selected = $this->selectedParticipantIds();

        if ($selected === []) {
            $this->addError('participants', __('Pick at least one participant.'));

            return;
        }

        $inputs = null;
        $method = SplitMethod::from($this->split_method);

        if ($method !== SplitMethod::Equal) {
            $inputs = [];
            foreach ($selected as $id) {
                $raw = $this->shareInputs[$id] ?? '';
                $inputs[$id] = $method === SplitMethod::Exact ? (int) $raw : (float) $raw;
            }
        }

        try {
            $action->execute(
                group: $this->group,
                payer: User::query()->findOrFail($this->payer_id),
                description: $this->description,
                totalAmount: $this->total_amount,
                method: $method,
                expenseDate: now()->parse($this->expense_date),
                participantIds: $selected,
                shareInputs: $inputs,
            );
        } catch (ExpenseException $e) {
            $this->addError('total_amount', $e->getMessage());

            return;
        }

        $this->dispatch('expense-saved');

        Flux::modal('add-expense-'.$this->group->getKey())->close();
        Flux::toast(variant: 'success', text: __('Expense saved.'));

        $this->resetForm();
    }

    private function discardReceipt(): void
    {
        if ($this->receipt instanceof TemporaryUploadedFile) {
            try {
                $this->receipt->delete();
            } catch (\Throwable) {
                // Livewire cleans tmp uploads on its own; ignore filesystem races.
            }
        }

        $this->receipt = null;
    }

    private function resetForm(): void
    {
        $this->reset(['description', 'total_amount', 'split_method', 'shareInputs']);
        $this->expense_date = now()->toDateString();
        $this->payer_id = (int) Auth::id();
        $this->shareInputs = [];
        $this->participants = [];
        $this->dismissOcr();
        $this->discardReceipt();

        foreach ($this->groupMemberIds() as $id) {
            $this->participants[$id] = 1;
            $this->shareInputs[$id] = '';
        }
    }

    /**
     * @return array<int>
     */
    private function selectedParticipantIds(): array
    {
        $ids = [];
        foreach ($this->participants as $userId => $checked) {
            if ($checked) {
                $ids[] = (int) $userId;
            }
        }

        return $ids;
    }

    /**
     * @return array<int>
     */
    private function groupMemberIds(): array
    {
        return $this->group->groupMembers()
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
