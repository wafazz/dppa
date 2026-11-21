<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\EmailCatalog;
use App\Services\EmailTemplate;
use App\Services\EmailTemplateSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Lets an administrator change the wording of the automatic e-mails. Only the text changes; the layout,
 * logo and footer are fixed.
 */
class EmailTemplateController extends Controller
{
    public function __construct(
        private EmailTemplateSettings $settings,
        private EmailTemplate $templates,
    ) {}

    /**
     * Every e-mail type, grouped, with which languages have been changed.
     */
    public function index(): JsonResponse
    {
        $changed = $this->settings->customizedMap();

        $types = collect(EmailCatalog::keys())->map(fn (string $type): array => [
            'key' => $type,
            'group' => EmailCatalog::definition($type)['group'],
            'label' => __("app.emails.types.{$type}.label"),
            'customized' => $changed[$type],
        ])->values();

        return response()->json(['data' => $types]);
    }

    public function show(string $type, string $locale): JsonResponse
    {
        $this->ensureExists($type, $locale);

        return response()->json(['data' => $this->payload($type, $locale)]);
    }

    public function update(Request $request, string $type, string $locale): JsonResponse
    {
        $this->ensureExists($type, $locale);

        $validated = $this->validated($request, $type);

        $this->settings->save($type, $locale, $validated, $request->user()->id);

        return response()->json(['data' => $this->payload($type, $locale)]);
    }

    /**
     * Removes the saved wording for this language, so the default is used again.
     */
    public function destroy(string $type, string $locale): JsonResponse
    {
        $this->ensureExists($type, $locale);

        $this->settings->reset($type, $locale);

        return response()->json(['data' => $this->payload($type, $locale)]);
    }

    /**
     * Renders the wording from the request with sample values, without saving it.
     */
    public function preview(Request $request, string $type, string $locale): JsonResponse
    {
        $this->ensureExists($type, $locale);

        $draft = $this->validated($request, $type);

        $message = $this->templates->compose(
            $type,
            EmailCatalog::SAMPLE_VALUES,
            route('login'),
            $locale,
            [],
            $draft,
            inlineLogo: false,
        );

        return response()->json(['data' => $message]);
    }

    /**
     * @return array{subject: string, body: string, button: ?string}
     */
    private function validated(Request $request, string $type): array
    {
        $hasButton = EmailCatalog::definition($type)['button'];

        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:5000'],
            'button' => [$hasButton ? 'required' : 'nullable', 'string', 'max:80'],
        ]);

        return [
            'subject' => trim($validated['subject']),
            'body' => trim($validated['body']),
            'button' => $hasButton ? trim((string) $validated['button']) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(string $type, string $locale): array
    {
        $current = $this->settings->current($type, $locale);
        $definition = EmailCatalog::definition($type);

        return [
            'type' => $type,
            'locale' => $locale,
            'label' => __("app.emails.types.{$type}.label"),
            'has_button' => $definition['button'],
            'placeholders' => array_map(fn (string $name): string => '{'.$name.'}', $definition['placeholders']),
            'subject' => $current['subject'],
            'body' => $current['body'],
            'button' => $current['button'],
            'customized' => $current['customized'],
            'defaults' => $this->settings->defaults($type, $locale),
        ];
    }

    private function ensureExists(string $type, string $locale): void
    {
        abort_unless(EmailCatalog::exists($type) && in_array($locale, EmailTemplateSettings::LOCALES, true), 404);
    }
}
