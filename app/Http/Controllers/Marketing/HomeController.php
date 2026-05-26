<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('marketing/home', [
            'features' => [
                [
                    'title' => 'Multi-tenant from day one',
                    'description' => 'Path-based tenancy with a resolver abstraction so you can ship subdomains and custom domains later without rewriting middleware.',
                    'icon' => 'building',
                ],
                [
                    'title' => 'Multi-gateway billing',
                    'description' => 'Stripe, PayPal, Paymob, HyperPay, HitPay — pluggable gateway registry with one canonical service.',
                    'icon' => 'credit-card',
                ],
                [
                    'title' => 'Admin scope built in',
                    'description' => 'Impersonation, audit log, webhook event replay — the back office that every SaaS eventually needs.',
                    'icon' => 'shield',
                ],
                [
                    'title' => 'Typed end-to-end',
                    'description' => 'Wayfinder gives you typed routes from Laravel to React. Never hand-write an href again.',
                    'icon' => 'code',
                ],
                [
                    'title' => 'Notifications & email',
                    'description' => 'Markdown email templates, in-app notification bell, per-user preferences matrix.',
                    'icon' => 'mail',
                ],
                [
                    'title' => 'Compliance ready',
                    'description' => 'GDPR export, login alerts, password breach check, audit log — fewer questionnaire surprises.',
                    'icon' => 'lock',
                ],
            ],
        ]);
    }
}
