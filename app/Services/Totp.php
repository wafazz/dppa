<?php

namespace App\Services;

use RuntimeException;

/**
 * Time-based one-time passwords (RFC 6238) for authenticator apps: HMAC-SHA1, 6 digits, 30-second steps.
 */
class Totp
{
    public const DIGITS = 6;

    public const PERIOD = 30;

    /** Steps accepted either side of the current one, to allow for clock drift. */
    public const WINDOW = 1;

    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public function generateSecret(): string
    {
        return $this->base32Encode(random_bytes(20));
    }

    public function codeForStep(string $secret, int $step): string
    {
        $key = $this->base32Decode($secret);
        $hash = hash_hmac('sha1', pack('J', $step), $key, true);

        $offset = ord($hash[strlen($hash) - 1]) & 0x0F;
        $binary = ((ord($hash[$offset]) & 0x7F) << 24)
            | (ord($hash[$offset + 1]) << 16)
            | (ord($hash[$offset + 2]) << 8)
            | ord($hash[$offset + 3]);

        return str_pad((string) ($binary % (10 ** self::DIGITS)), self::DIGITS, '0', STR_PAD_LEFT);
    }

    /**
     * The step that matches the code, or null. A step that was already used is refused, so a code cannot be replayed.
     */
    public function matchingStep(string $secret, string $code, int $timestamp, ?int $lastUsedStep): ?int
    {
        if (preg_match('/^\d{'.self::DIGITS.'}$/', $code) !== 1) {
            return null;
        }

        $current = intdiv($timestamp, self::PERIOD);

        for ($offset = -self::WINDOW; $offset <= self::WINDOW; $offset++) {
            $step = $current + $offset;

            if ($lastUsedStep !== null && $step <= $lastUsedStep) {
                continue;
            }

            if (hash_equals($this->codeForStep($secret, $step), $code)) {
                return $step;
            }
        }

        return null;
    }

    public function otpauthUri(string $secret, string $accountLabel, string $issuer): string
    {
        return 'otpauth://totp/'.rawurlencode($issuer.':'.$accountLabel)
            .'?secret='.$secret
            .'&issuer='.rawurlencode($issuer)
            .'&algorithm=SHA1&digits='.self::DIGITS.'&period='.self::PERIOD;
    }

    public function base32Encode(string $binary): string
    {
        $bits = '';

        foreach (str_split($binary) as $character) {
            $bits .= str_pad(decbin(ord($character)), 8, '0', STR_PAD_LEFT);
        }

        $encoded = '';

        foreach (str_split($bits, 5) as $chunk) {
            $encoded .= self::ALPHABET[bindec(str_pad($chunk, 5, '0'))];
        }

        return $encoded;
    }

    public function base32Decode(string $encoded): string
    {
        $bits = '';

        foreach (str_split(strtoupper($encoded)) as $character) {
            $position = strpos(self::ALPHABET, $character);

            if ($position === false) {
                throw new RuntimeException('Invalid base32 character.');
            }

            $bits .= str_pad(decbin($position), 5, '0', STR_PAD_LEFT);
        }

        $binary = '';

        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $binary .= chr(bindec($byte));
            }
        }

        return $binary;
    }
}
