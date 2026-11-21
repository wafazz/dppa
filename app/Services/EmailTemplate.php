<?php

namespace App\Services;

/**
 * Builds the subject and HTML of every templated e-mail, with the uploaded logo in the layout.
 */
class EmailTemplate
{
    public function __construct(
        private Branding $branding,
        private EmailTemplateSettings $settings,
    ) {}

    /**
     * @param  array<string, scalar>  $values  Placeholder values, for example ['name' => 'Ali'] for {name}.
     * @param  array<int, string>  $extraLines  Lines added after the template body, such as a list.
     * @param  ?array{subject: string, body: string, button: ?string}  $draft  Wording to use instead of the saved one (preview).
     * @return array{subject: string, html: string}
     */
    public function compose(
        string $type,
        array $values,
        ?string $actionUrl = null,
        ?string $locale = null,
        array $extraLines = [],
        ?array $draft = null,
        bool $inlineLogo = true,
    ): array {
        $locale ??= app()->getLocale();
        $wording = $draft ?? $this->settings->current($type, $locale);

        $replace = [];

        foreach ($values as $name => $value) {
            $replace['{'.$name.'}'] = (string) $value;
        }

        $paragraphs = preg_split('/\R\s*\R/', trim((string) $wording['body'])) ?: [];
        $lines = array_values(array_filter(array_map(
            fn (string $paragraph): string => strtr(trim($paragraph), $replace),
            $paragraphs,
        ), fn (string $paragraph): bool => $paragraph !== ''));

        $buttonText = $wording['button'] !== null ? strtr($wording['button'], $replace) : null;

        return [
            'subject' => strtr((string) $wording['subject'], $replace),
            'html' => $this->html(array_merge($lines, $extraLines), $buttonText, $actionUrl, $inlineLogo),
        ];
    }

    /**
     * @param  array<int, string>  $lines  Plain-text paragraphs. The view escapes them.
     */
    public function html(array $lines, ?string $actionText = null, ?string $actionUrl = null, bool $inlineLogo = true): string
    {
        // Sent e-mail refers to the logo inline (see ResendMailer). The editor preview uses the public address.
        $logo = $this->branding->logoUrl();

        return view('emails.message', [
            'systemName' => config('app.name'),
            'logoUrl' => $logo === null ? null : ($inlineLogo ? 'cid:'.ResendMailer::LOGO_CID : $logo),
            'lines' => $lines,
            'actionText' => $actionText,
            'actionUrl' => $actionUrl,
        ])->render();
    }
}
