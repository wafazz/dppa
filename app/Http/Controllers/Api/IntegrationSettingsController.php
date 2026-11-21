<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ActivityLogger;
use App\Services\EmailTemplate;
use App\Services\IntegrationSettings;
use App\Services\ResendMailer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class IntegrationSettingsController extends Controller
{
    /**
     * Settings grouped by card. Each card is saved as one unit with its own Save button.
     * Card name => [form field => [stored key, validation rules]].
     */
    private const CARDS = [
        'resend' => [
            'api_key' => ['resend.api_key', ['nullable', 'string', 'max:255']],
            'from_email' => ['resend.from_email', ['nullable', 'email:rfc', 'max:190']],
            'from_name' => ['resend.from_name', ['nullable', 'string', 'max:100']],
        ],
        'onsend' => [
            'api_key' => ['onsend.api_key', ['nullable', 'string', 'max:255']],
            'base_url' => ['onsend.base_url', ['nullable', 'url:https', 'max:255']],
            'sender_id' => ['onsend.sender_id', ['nullable', 'string', 'max:100']],
        ],
    ];

    /**
     * Current non-secret settings. API keys are reported only as saved or not saved.
     */
    public function show(IntegrationSettings $settings): JsonResponse
    {
        return response()->json(['data' => $settings->summary()]);
    }

    /**
     * Save every field of one card. A blank API key keeps the saved key; other blank fields are cleared.
     */
    public function update(Request $request, string $card, IntegrationSettings $settings): JsonResponse
    {
        abort_unless(isset(self::CARDS[$card]), 404);

        $fields = self::CARDS[$card];

        $rules = [];
        foreach ($fields as $name => [$key, $fieldRules]) {
            $rules[$name] = $fieldRules;
        }

        $validated = $request->validate($rules);

        foreach ($fields as $name => [$key]) {
            $settings->put($key, $validated[$name] ?? null, $request->user()->id);
        }

        return $this->show($settings);
    }

    /**
     * Send a test email through Resend to the address given by the administrator.
     */
    public function testResend(Request $request, ResendMailer $mailer, EmailTemplate $templates, IntegrationSettings $settings): JsonResponse
    {
        $validated = $request->validate([
            'to' => ['required', 'email:rfc', 'max:190'],
        ]);

        try {
            $id = $mailer->send(
                $validated['to'],
                __('app.settings.test_subject'),
                $templates->html([__('app.settings.test_body')]),
            );
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        $settings->markVerified('resend', $request->user()->id);
        app(ActivityLogger::class)->record('setting.test_email', $validated['to'], $request->user(), null, ['to' => $validated['to']]);

        return response()->json(['message' => __('app.settings.test_sent'), 'id' => $id]);
    }
}
