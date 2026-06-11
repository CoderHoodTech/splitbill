<?php

declare(strict_types=1);

namespace App\Livewire\Notifications;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Bell extends Component
{
    public bool $open = false;

    public function toggle(): void
    {
        $this->open = ! $this->open;
    }

    /**
     * Unread count for the bell badge.
     */
    #[Computed]
    public function unreadCount(): int
    {
        return Auth::user()->unreadNotifications()->count();
    }

    /**
     * Latest 10 notifications (both read + unread) to render in the dropdown.
     *
     * @return Collection<int, DatabaseNotification>
     */
    #[Computed]
    public function recent(): Collection
    {
        return Auth::user()
            ->notifications()
            ->latest()
            ->limit(10)
            ->get();
    }

    public function markAsRead(string $id): void
    {
        $notification = Auth::user()->notifications()->whereKey($id)->first();

        if ($notification === null) {
            return;
        }

        $notification->markAsRead();

        unset($this->unreadCount, $this->recent);
    }

    public function markAllAsRead(): void
    {
        Auth::user()->unreadNotifications()->update(['read_at' => now()]);

        unset($this->unreadCount, $this->recent);
    }

    public function render()
    {
        return view('livewire.notifications.bell');
    }
}
