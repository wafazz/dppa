<?php

namespace App\Services;

use HTMLPurifier;
use HTMLPurifier_Config;

/**
 * Cleans rich-text input before it is stored and produces plain text for search and export.
 */
class HtmlSanitizer
{
    private ?HTMLPurifier $purifier = null;

    /**
     * Remove every tag and attribute that is not part of the allowed rich-text subset.
     */
    public function sanitize(?string $html): string
    {
        return trim($this->purifier()->purify((string) $html));
    }

    /**
     * Convert rich-text HTML into plain text for search, CSV export and empty-field checks.
     */
    public function toPlainText(?string $html): string
    {
        $withLineBreaks = preg_replace('/<\/(p|li|blockquote)>|<br\s*\/?>/i', "\n", (string) $html) ?? '';
        $text = html_entity_decode(strip_tags($withLineBreaks), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/[ \t]+/', ' ', $text) ?? '');
    }

    /**
     * Determine whether rich-text input contains visible text.
     */
    public function hasText(?string $html): bool
    {
        return $this->toPlainText($html) !== '';
    }

    /**
     * Build the purifier once per request with the allowed formatting set.
     */
    private function purifier(): HTMLPurifier
    {
        if ($this->purifier === null) {
            $config = HTMLPurifier_Config::createDefault();
            $config->set('HTML.Allowed', 'p,br,strong,em,u,s,ol,ul,li,blockquote,a[href]');
            $config->set('HTML.Nofollow', true);
            $config->set('HTML.TargetBlank', true);
            $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true]);
            $config->set('Cache.DefinitionImpl', null);

            $this->purifier = new HTMLPurifier($config);
        }

        return $this->purifier;
    }
}
