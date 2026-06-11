<?php

declare(strict_types=1);

namespace App\Enums;

enum GroupMemberRole: string
{
    case Owner = 'owner';
    case Member = 'member';
}
