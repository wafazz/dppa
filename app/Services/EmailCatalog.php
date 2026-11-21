<?php

namespace App\Services;

/**
 * Every templated e-mail the system sends. Default wording is in the app language files under emails.types, and
 * an administrator's changes are stored in app_settings. Placeholders are listed here so the editor can show them.
 */
class EmailCatalog
{
    /**
     * @var array<string, array{group: string, placeholders: array<int, string>, button: bool}>
     */
    public const TYPES = [
        'account_setup' => ['group' => 'accounts', 'placeholders' => ['name', 'hours'], 'button' => true],
        'account_deactivated' => ['group' => 'accounts', 'placeholders' => ['name'], 'button' => false],
        'account_reactivated' => ['group' => 'accounts', 'placeholders' => ['name'], 'button' => true],
        'member_verify' => ['group' => 'membership', 'placeholders' => ['name', 'business', 'hours'], 'button' => true],
        'member_verify_reminder' => ['group' => 'membership', 'placeholders' => ['name', 'business', 'hours'], 'button' => true],
        'member_verified' => ['group' => 'membership', 'placeholders' => ['name', 'business'], 'button' => false],
        'application_approved' => ['group' => 'membership', 'placeholders' => ['name', 'business', 'member_no'], 'button' => false],
        'application_rejected' => ['group' => 'membership', 'placeholders' => ['name', 'business'], 'button' => false],
        'invitation' => ['group' => 'membership', 'placeholders' => ['date'], 'button' => true],
        'password_reset' => ['group' => 'security', 'placeholders' => ['name', 'hours'], 'button' => true],
        'two_factor_sign_in' => ['group' => 'security', 'placeholders' => ['name', 'code', 'minutes'], 'button' => false],
        'two_factor_change' => ['group' => 'security', 'placeholders' => ['name', 'code', 'minutes'], 'button' => false],
        'new_device_sign_in' => ['group' => 'security', 'placeholders' => ['name', 'time', 'ip'], 'button' => true],
        'appointment_made' => ['group' => 'appointments', 'placeholders' => ['name', 'position', 'term_start'], 'button' => false],
        'appointment_term_ending' => ['group' => 'appointments', 'placeholders' => ['name', 'position', 'term_end', 'days'], 'button' => false],
        'vote_opened' => ['group' => 'votes', 'placeholders' => ['name', 'vote', 'closes'], 'button' => true],
        'vote_closing' => ['group' => 'votes', 'placeholders' => ['name', 'vote', 'closes', 'hours'], 'button' => true],
        'vote_results' => ['group' => 'votes', 'placeholders' => ['name', 'vote'], 'button' => true],
        'management_digest' => ['group' => 'digest', 'placeholders' => ['count'], 'button' => true],
    ];

    /**
     * Example values shown in the editor preview.
     *
     * @var array<string, string>
     */
    public const SAMPLE_VALUES = [
        'name' => 'Ali bin Abu',
        'business' => 'Kedai Ali Enterprise',
        'member_no' => 'DPPA-2026-0001',
        'hours' => '72',
        'minutes' => '10',
        'code' => '123456',
        'count' => '3',
        'date' => '1 Jan 2027, 10:00',
        'time' => '21 Nov 2026, 14:05',
        'ip' => '203.0.113.5',
        'position' => 'Pengerusi',
        'term_start' => '1 Jan 2027',
        'term_end' => '31 Dec 2027',
        'days' => '30',
        'vote' => 'Pemilihan AJK 2027',
        'closes' => '31 Jan 2027, 17:00',
    ];

    /**
     * @return array<int, string>
     */
    public static function keys(): array
    {
        return array_keys(self::TYPES);
    }

    public static function exists(string $type): bool
    {
        return array_key_exists($type, self::TYPES);
    }

    /**
     * @return array{group: string, placeholders: array<int, string>, button: bool}
     */
    public static function definition(string $type): array
    {
        return self::TYPES[$type];
    }
}
