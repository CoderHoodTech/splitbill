<?php

declare(strict_types=1);

namespace App\Livewire\Friends;

use App\Actions\Friends\AcceptFriendRequest;
use App\Actions\Friends\DeclineFriendRequest;
use App\Exceptions\FriendRequestException;
use App\Models\FriendRequest;
use App\Models\User;
use Flux\Flux;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class FriendList extends Component
{
    public string $tab = 'friends';

    public function setTab(string $tab): void
    {
        if (! in_array($tab, ['friends', 'incoming', 'sent'], true)) {
            return;
        }

        $this->tab = $tab;
    }

    #[On('friend-request-sent')]
    public function refresh(): void
    {
        unset($this->friends, $this->incoming, $this->sent);
    }

    #[Computed]
    public function friends(): Collection
    {
        return Auth::user()
            ->friends()
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, FriendRequest>
     */
    #[Computed]
    public function incoming(): Collection
    {
        return Auth::user()
            ->receivedFriendRequests()
            ->with('sender')
            ->pending()
            ->latest()
            ->get();
    }

    /**
     * @return Collection<int, FriendRequest>
     */
    #[Computed]
    public function sent(): Collection
    {
        return Auth::user()
            ->sentFriendRequests()
            ->with('receiver')
            ->pending()
            ->latest()
            ->get();
    }

    public function accept(int $requestId, AcceptFriendRequest $action): void
    {
        $request = $this->authorizedRequest($requestId, 'accept');

        try {
            $action->execute(Auth::user(), $request);
        } catch (FriendRequestException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        }

        $this->refresh();

        Flux::toast(variant: 'success', text: __('You are now friends with :name.', [
            'name' => $request->sender?->name ?? __('your friend'),
        ]));
    }

    public function decline(int $requestId, DeclineFriendRequest $action): void
    {
        $request = $this->authorizedRequest($requestId, 'decline');

        try {
            $action->execute(Auth::user(), $request);
        } catch (FriendRequestException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        }

        $this->refresh();

        Flux::toast(variant: 'success', text: __('Friend request declined.'));
    }

    private function authorizedRequest(int $id, string $ability): FriendRequest
    {
        $request = FriendRequest::query()->findOrFail($id);

        /** @var User $user */
        $user = Auth::user();

        if (! $user->can($ability, $request)) {
            throw new AuthorizationException;
        }

        return $request;
    }
}
