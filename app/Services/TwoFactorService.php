<?php

namespace App\Services;

use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Two-factor sign-in. Each method is switched on by the account holder. A method can be used at sign-in
 * only when its channel is connected: the authenticator app always, email when Resend is connected, and
 * WhatsApp when OnSend is connected.
 */
class TwoFactorService
{
    public const METHODS = ['authenticator', 'email', 'whatsapp'];

    public const CODE_TTL_MINUTES = 10;

    public const RESEND_COOLDOWN_SECONDS = 60;

    public const MAX_EMAIL_ATTEMPTS = 5;

    public function __construct(
        private IntegrationSettings $settings,
        private ResendMailer $mailer,
        private EmailTemplate $templates,
        private Totp $totp,
        private ActivityLogger $activity,
    ) {}

    /**
     * Methods the account holder has switched on, whether or not their channel is connected right now.
     *
     * @return array<int, string>
     */
    public function enabledMethods(User $user): array
    {
        return array_values(array_filter([
            $user->two_factor_authenticator_confirmed_at !== null ? 'authenticator' : null,
            $user->two_factor_email_enabled ? 'email' : null,
            $user->two_factor_whatsapp_enabled ? 'whatsapp' : null,
        ]));
    }

    /**
     * Enabled methods whose channel is connected. These are the ones offered at sign-in.
     *
     * @return array<int, string>
     */
    public function usableMethods(User $user): array
    {
        return array_values(array_filter(
            $this->enabledMethods($user),
            fn (string $method): bool => $this->channelConnected($method),
        ));
    }

    public function channelConnected(string $method): bool
    {
        return match ($method) {
            'authenticator' => true,
            'email' => $this->settings->isConnected('resend'),
            'whatsapp' => $this->settings->isConnected('onsend'),
            default => false,
        };
    }

    /**
     * Sends a one-time code to the account's email. Only the hash is kept, for a short time.
     *
     * @param  string  $purpose  'sign-in', 'enable' or 'disable'
     *
     * @throws RuntimeException When the cooldown is active or Resend rejects the message.
     */
    public function sendEmailCode(User $user, string $purpose): void
    {
        $cooldownKey = $this->emailKey($user, $purpose).':cooldown';

        if (Cache::has($cooldownKey)) {
            throw new RuntimeException(__('app.two_factor.error_cooldown', ['seconds' => self::RESEND_COOLDOWN_SECONDS]));
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $isSignIn = $purpose === 'sign-in';

        $message = $this->templates->compose(
            $isSignIn ? 'two_factor_sign_in' : 'two_factor_change',
            ['name' => $user->name, 'code' => $code, 'minutes' => self::CODE_TTL_MINUTES],
        );

        $this->mailer->send($user->email, $message['subject'], $message['html']);

        Cache::put(
            $this->emailKey($user, $purpose),
            ['hash' => Hash::make($code), 'attempts' => 0],
            now()->addMinutes(self::CODE_TTL_MINUTES),
        );
        Cache::put($cooldownKey, true, now()->addSeconds(self::RESEND_COOLDOWN_SECONDS));
    }

    /**
     * Checks an emailed code. A wrong code counts against the attempts; the code is discarded when they run out.
     */
    public function verifyEmailCode(User $user, string $code, string $purpose): bool
    {
        $key = $this->emailKey($user, $purpose);
        $stored = Cache::get($key);

        if (! is_array($stored)) {
            return false;
        }

        if (preg_match('/^\d{6}$/', $code) === 1 && Hash::check($code, $stored['hash'])) {
            Cache::forget($key);

            return true;
        }

        $stored['attempts']++;

        if ($stored['attempts'] >= self::MAX_EMAIL_ATTEMPTS) {
            Cache::forget($key);
        } else {
            Cache::put($key, $stored, now()->addMinutes(self::CODE_TTL_MINUTES));
        }

        return false;
    }

    public function hasPendingEmailCode(User $user, string $purpose): bool
    {
        return Cache::has($this->emailKey($user, $purpose));
    }

    /**
     * Checks an authenticator-app code and records its time step so it cannot be used twice.
     */
    public function verifyAuthenticator(User $user, string $code): bool
    {
        if ($user->two_factor_authenticator_secret === null || $user->two_factor_authenticator_confirmed_at === null) {
            return false;
        }

        $step = $this->totp->matchingStep(
            $user->two_factor_authenticator_secret,
            $code,
            now()->timestamp,
            $user->two_factor_authenticator_last_step,
        );

        if ($step === null) {
            return false;
        }

        $user->forceFill(['two_factor_authenticator_last_step' => $step])->save();

        return true;
    }

    /**
     * Starts (or restarts) authenticator setup. Nothing is switched on until the first code is confirmed.
     *
     * @return array{secret: string, otpauth_uri: string, qr_data_uri: string}
     *
     * @throws RuntimeException When the authenticator is already on.
     */
    public function startAuthenticatorSetup(User $user): array
    {
        if ($user->two_factor_authenticator_confirmed_at !== null) {
            throw new RuntimeException(__('app.two_factor.error_already_on'));
        }

        $secret = $this->totp->generateSecret();

        $user->forceFill([
            'two_factor_authenticator_secret' => $secret,
            'two_factor_authenticator_confirmed_at' => null,
            'two_factor_authenticator_last_step' => null,
        ])->save();

        $otpauthUri = $this->totp->otpauthUri($secret, $user->email, config('app.name'));

        return [
            'secret' => $secret,
            'otpauth_uri' => $otpauthUri,
            'qr_data_uri' => $this->qrDataUri($otpauthUri),
        ];
    }

    /**
     * @throws RuntimeException When there is no pending setup.
     */
    public function confirmAuthenticator(User $user, string $code): bool
    {
        if ($user->two_factor_authenticator_secret === null) {
            throw new RuntimeException(__('app.two_factor.error_no_setup'));
        }

        $step = $this->totp->matchingStep(
            $user->two_factor_authenticator_secret,
            $code,
            now()->timestamp,
            $user->two_factor_authenticator_last_step,
        );

        if ($step === null) {
            return false;
        }

        $user->forceFill([
            'two_factor_authenticator_confirmed_at' => now(),
            'two_factor_authenticator_last_step' => $step,
        ])->save();

        $this->activity->record('two_factor.enabled', 'authenticator', $user, $user, ['method' => 'authenticator']);

        return true;
    }

    public function enableEmail(User $user): void
    {
        $user->forceFill(['two_factor_email_enabled' => true])->save();

        $this->activity->record('two_factor.enabled', 'email', $user, $user, ['method' => 'email']);
    }

    public function disableMethod(User $user, string $method): void
    {
        $this->activity->record('two_factor.disabled', $method, $user, $user, ['method' => $method]);

        match ($method) {
            'authenticator' => $user->forceFill([
                'two_factor_authenticator_secret' => null,
                'two_factor_authenticator_confirmed_at' => null,
                'two_factor_authenticator_last_step' => null,
            ])->save(),
            'email' => $user->forceFill(['two_factor_email_enabled' => false])->save(),
            'whatsapp' => $user->forceFill(['two_factor_whatsapp_enabled' => false])->save(),
            default => throw new RuntimeException(__('app.two_factor.error_unknown_method')),
        };
    }

    /**
     * The setup link drawn as an SVG QR code, returned as an image the browser can show without running any code.
     */
    private function qrDataUri(string $content): string
    {
        $renderer = new ImageRenderer(new RendererStyle(240, 2), new SvgImageBackEnd);
        $svg = (new Writer($renderer))->writeString($content);

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    private function emailKey(User $user, string $purpose): string
    {
        return "two-factor:email:{$user->id}:{$purpose}";
    }
}
