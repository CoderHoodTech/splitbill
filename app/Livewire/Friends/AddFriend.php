<?php

declare(strict_types=1);

namespace App\Livewire\Friends;

use App\Actions\Friends\SendFriendRequest;
use App\Exceptions\FriendRequestException;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Validate;
use Livewire\Component;

class AddFriend extends Component
{
    /**
     * Caps how often a user can probe friend codes from the web UI, so codes
     * can't be enumerated. Mirrors the `friend-code-lookup` limiter applied
     * to the matching API endpoint.
     */
    private const LOOKUP_LIMIT = 10;

    private const LOOKUP_DECAY_SECONDS = 60;

    #[Validate('required|string|min:4|max:16')]
    public string $friend_code = '';

    public function send(SendFriendRequest $action): void
    {
        $this->validate();

        $key = $this->rateLimitKey();

        if (RateLimiter::tooManyAttempts($key, self::LOOKUP_LIMIT)) {
            $seconds = RateLimiter::availableIn($key);
            $this->addError('friend_code', __('Too many attempts. Please wait :seconds seconds before trying again.', ['seconds' => $seconds]));

            return;
        }

        RateLimiter::hit($key, self::LOOKUP_DECAY_SECONDS);

        try {
            $action->execute(Auth::user(), $this->friend_code);
        } catch (FriendRequestException $e) {
            $this->addError('friend_code', $e->getMessage());

            return;
        }

        $this->reset('friend_code');

        Flux::toast(variant: 'success', text: __('Friend request sent.'));

        $this->dispatch('friend-request-sent');
    }

    private function rateLimitKey(): string
    {
        return 'friend-code-lookup:'.Auth::id();
    }
}
