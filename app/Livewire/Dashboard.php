<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Actions\Dashboard\CalculateUserDashboard;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Dashboard extends Component
{
    /**
     * The authenticated user's cross-group summary.
     *
     * @return array<string, mixed>
     */
    #[Computed]
    public function summary(): array
    {
        return app(CalculateUserDashboard::class)->execute(Auth::user());
    }

    public function render(): View
    {
        return view('livewire.dashboard');
    }
}
