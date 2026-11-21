<?php

namespace App\Enums;

enum MemberStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('app.enums.member_status.pending'),
            self::Approved => __('app.enums.member_status.approved'),
            self::Rejected => __('app.enums.member_status.rejected'),
        };
    }
}
