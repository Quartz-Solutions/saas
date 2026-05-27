<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\CmsForm;
use App\Models\CmsFormSubmission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

/**
 * Public form submission endpoint. POST /marketing/forms/{slug}.
 *
 * - Honeypot: any non-empty `_honey` field rejects the submission as spam.
 * - Throttled at the route definition layer.
 * - Validates against the form's declared field schema.
 * - Persists to cms_form_submissions when `store_submissions=true`.
 * - Fires email + webhook notifications if configured.
 */
class FormSubmissionsController extends Controller
{
    public function store(Request $request, string $slug): JsonResponse
    {
        $form = CmsForm::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->first();

        if ($form === null) {
            // Return a clean JSON 404 rather than letting Eloquent's
            // ModelNotFoundException surface ("No query results for
            // model [App\Models\CmsForm]") in dev mode. Block authors
            // get a helpful hint pointing at the admin.
            return response()->json([
                'ok' => false,
                'message' => "Form [{$slug}] does not exist or is inactive. Create it in Admin → CMS → Forms.",
            ], 404);
        }

        // Honeypot
        if (filled($request->input('_honey'))) {
            return response()->json(['ok' => true]); // pretend success
        }

        $fields = (array) ($form->fields ?? []);
        $rules = [];
        foreach ($fields as $field) {
            $key = (string) ($field['key'] ?? '');
            if ($key === '') {
                continue;
            }
            $required = (bool) ($field['required'] ?? false);
            $type = (string) ($field['type'] ?? 'text');

            $rule = $required ? ['required'] : ['nullable'];
            $rule[] = match ($type) {
                'email' => 'email',
                'tel' => 'string',
                'number' => 'numeric',
                'url' => 'string',
                'checkbox' => 'boolean',
                default => 'string',
            };
            if ($type === 'textarea' || $type === 'text') {
                $rule[] = 'max:10000';
            }
            $rules[$key] = $rule;
        }

        $data = Validator::make($request->all(), $rules)->validate();

        if ($form->store_submissions) {
            CmsFormSubmission::query()->create([
                'cms_form_id' => $form->id,
                'payload' => $data,
                'ip' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 255),
                'referer' => substr((string) $request->headers->get('referer'), 0, 2048),
            ]);
        }

        if ($form->notify_email) {
            try {
                Mail::raw(
                    "New submission for {$form->name}:\n\n".json_encode($data, JSON_PRETTY_PRINT),
                    fn ($m) => $m->to($form->notify_email)->subject("New: {$form->name}"),
                );
            } catch (\Throwable) {
                // Don't break the submission on mail failure.
            }
        }

        if ($form->webhook_url) {
            try {
                Http::timeout(5)->post($form->webhook_url, [
                    'form' => $form->slug,
                    'data' => $data,
                    'received_at' => now()->toIso8601String(),
                ]);
            } catch (\Throwable) {
                // Don't break the submission on webhook failure.
            }
        }

        return response()->json([
            'ok' => true,
            'message' => $form->success_message ?: 'Thanks! Your submission has been received.',
        ]);
    }
}
