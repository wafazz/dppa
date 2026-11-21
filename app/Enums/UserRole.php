<?php

namespace App\Enums;

/**
 * Access role of a staff account. Superadmin and admin accounts are governed by AccountType instead.
 */
enum UserRole: string
{
    case Committee = 'committee';
    case Viewer = 'viewer';

    public function label(): string
    {
        return match ($this) {
            self::Committee => __('app.enums.user_role.committee'),
            self::Viewer => __('app.enums.user_role.viewer'),
        };
    }
}
