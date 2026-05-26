<?php

/*
|--------------------------------------------------------------------------
| Billing override reasons
|--------------------------------------------------------------------------
|
| Catalog of reasons surfaced in the Super Admin "Apply credit" / "Comp
| months" / "Refund" / "Manual payment" dialogs. Stored verbatim with
| each action on the subscription/payment for audit trail.
|
| Slugs are stable; labels are display-only.
|
*/

return [

    'credit' => [
        'goodwill' => 'Goodwill / customer satisfaction',
        'incident' => 'Service incident credit',
        'manual_billing_correction' => 'Manual billing correction',
        'pre_sales_promise' => 'Pre-sales promise honored',
        'sales_discount' => 'Sales discount',
        'other' => 'Other (see admin notes)',
    ],

    'comp' => [
        'free_trial_extension' => 'Free trial extension',
        'service_incident' => 'Service incident comp',
        'enterprise_negotiation' => 'Enterprise negotiation',
        'feature_unavailable' => 'Promised feature still unavailable',
        'other' => 'Other (see admin notes)',
    ],

    'refund' => [
        'customer_request' => 'Customer request',
        'duplicate_charge' => 'Duplicate charge',
        'fraudulent' => 'Fraudulent charge',
        'service_not_received' => 'Service not received',
        'goodwill' => 'Goodwill',
        'other' => 'Other (see admin notes)',
    ],

    'cancellation' => [
        'customer_request' => 'Customer request',
        'fraud' => 'Fraud / chargeback',
        'non_payment' => 'Non-payment after dunning',
        'plan_archived' => 'Plan archived',
        'tos_violation' => 'Terms of Service violation',
        'admin_override' => 'Admin override',
        'other' => 'Other (see admin notes)',
    ],

    'manual_payment_method' => [
        'wire' => 'Wire transfer',
        'ach' => 'ACH',
        'check' => 'Cheque',
        'cash' => 'Cash',
        'po' => 'Purchase order',
        'other' => 'Other',
    ],
];
