<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Support\Newsletter\NewsletterProviderRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NewsletterController extends Controller
{
    public function __construct(private readonly NewsletterProviderRegistry $registry) {}

    public function subscribe(Request $request): JsonResponse
    {
        // Honeypot — keep it consistent with form submissions.
        if (filled($request->input('_honey'))) {
            return response()->json(['ok' => true]);
        }

        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'locale' => ['nullable', 'string', 'max:8'],
            'source' => ['nullable', 'string', 'max:64'],
        ]);

        $provider = $this->registry->active();
        $result = $provider->subscribe(
            email: $data['email'],
            locale: $data['locale'] ?? app()->getLocale(),
            source: $data['source'] ?? 'newsletter_block',
            ip: $request->ip(),
        );

        return response()->json([
            'ok' => (bool) ($result['ok'] ?? true),
            'message' => $result['message'] ?? 'Thanks! Check your inbox to confirm.',
            'provider' => $provider->id(),
        ]);
    }
}
