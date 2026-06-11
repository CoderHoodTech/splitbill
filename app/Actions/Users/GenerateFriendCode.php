<?php

declare(strict_types=1);

namespace App\Actions\Users;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Generate a unique, short, uppercase friend code for a user.
 *
 * Strategy: take the last 8 chars of a ULID (Crockford base32 = uppercase,
 * no ambiguous I/L/O/U), then re-roll on the unlikely event of a collision.
 * 8 chars = 40 bits of entropy, ~1 trillion possible codes.
 */
final class GenerateFriendCode
{
    public function execute(): string
    {
        do {
            $code = Str::upper(Str::substr((string) Str::ulid(), -8));
        } while (DB::table('users')->where('friend_code', $code)->exists());

        return $code;
    }
}
