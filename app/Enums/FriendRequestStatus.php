<?php

declare(strict_types=1);

namespace App\Enums;

enum FriendRequestStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Declined = 'declined';
}
