<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

class LegalController extends Controller
{
    /**
     * Placeholder legal page renderer. Each project replaces this with real,
     * lawyer-reviewed copy — the boilerplate ships only the structure.
     */
    public function show(string $type): Response
    {
        if (! array_key_exists($type, $this->knownTypes())) {
            abort(ResponseAlias::HTTP_NOT_FOUND);
        }

        return Inertia::render('marketing/legal/'.$type, [
            'type' => $type,
            'effectiveDate' => now()->startOfYear()->toDateString(),
            'companyName' => config('app.name'),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function knownTypes(): array
    {
        return [
            'privacy' => 'Privacy Policy',
            'terms' => 'Terms of Service',
            'cookies' => 'Cookie Policy',
        ];
    }
}
