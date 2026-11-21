<?php

namespace App\Services;

use App\Models\AppSetting;

/**
 * Reads and saves the wording of templated e-mails per type and language. An administrator's version is
 * kept in app_settings as JSON; without one, the lang defaults are used.
 */
class EmailTemplateSettings
{
    public const LOCALES = ['en', 'ms'];

    /**
     * The default wording in one language.
     *
     * @return array{subject: string, body: string, button: ?string}
     */
    public function defaults(string $type, string $locale): array
    {
        $lines = (array) trans("app.emails.types.{$type}.lines", [], $locale);

        return [
            'subject' => (string) trans("app.emails.types.{$type}.subject", [], $locale),
            'body' => implode("\n\n", $lines),
            'button' => EmailCatalog::definition($type)['button']
                ? (string) trans("app.emails.types.{$type}.button", [], $locale)
                : null,
        ];
    }

    /**
     * The wording in use: the administrator's version when one is saved, otherwise the default.
     *
     * @return array{subject: string, body: string, button: ?string, customized: bool}
     */
    public function current(string $type, string $locale): array
    {
        $saved = AppSetting::query()->find($this->key($type, $locale));
        $custom = $saved?->value !== null ? json_decode((string) $saved->value, true) : null;

        if (! is_array($custom)) {
            return $this->defaults($type, $locale) + ['customized' => false];
        }

        return [
            'subject' => (string) $custom['subject'],
            'body' => (string) $custom['body'],
            'button' => EmailCatalog::definition($type)['button'] ? (string) ($custom['button'] ?? '') : null,
            'customized' => true,
        ];
    }

    /**
     * @param  array{subject: string, body: string, button: ?string}  $values
     */
    public function save(string $type, string $locale, array $values, ?int $userId): void
    {
        AppSetting::query()->updateOrCreate(
            ['key' => $this->key($type, $locale)],
            [
                'value' => json_encode([
                    'subject' => $values['subject'],
                    'body' => $values['body'],
                    'button' => $values['button'],
                ], JSON_UNESCAPED_UNICODE),
                'is_secret' => false,
                'updated_by_user_id' => $userId,
            ],
        );
    }

    public function reset(string $type, string $locale): void
    {
        AppSetting::query()->whereKey($this->key($type, $locale))->delete();
    }

    /**
     * Which languages have an administrator's version, keyed by type.
     *
     * @return array<string, array<string, bool>>
     */
    public function customizedMap(): array
    {
        $saved = AppSetting::query()
            ->where('key', 'like', 'email.template.%')
            ->pluck('key')
            ->all();

        $map = [];

        foreach (EmailCatalog::keys() as $type) {
            foreach (self::LOCALES as $locale) {
                $map[$type][$locale] = in_array($this->key($type, $locale), $saved, true);
            }
        }

        return $map;
    }

    private function key(string $type, string $locale): string
    {
        return "email.template.{$type}.{$locale}";
    }
}
