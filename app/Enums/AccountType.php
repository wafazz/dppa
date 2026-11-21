<?php

namespace App\Enums;

/**
 * Kind of system account. Superadmin is the developer account; admin accounts are created by the superadmin.
 * Member accounts belong to approved members and only see their own pages and votes.
 */
enum AccountType: string
{
    case Superadmin = 'superadmin';
    case Admin = 'admin';
    case Staff = 'staff';
    case Member = 'member';

    public function label(): string
    {
        return match ($this) {
            self::Superadmin => __('app.enums.account_type.superadmin'),
            self::Admin => __('app.enums.account_type.admin'),
            self::Staff => __('app.enums.account_type.staff'),
            self::Member => __('app.enums.account_type.member'),
        };
    }

    /**
     * Only superadmin and admin accounts manage the system. Staff and member accounts never do.
     */
    public function isManagement(): bool
    {
        return $this === self::Superadmin || $this === self::Admin;
    }
}
