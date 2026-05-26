<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoice->number }}</title>
    <style>
        * { font-family: 'DejaVu Sans', sans-serif; }
        body { color: #111; margin: 0; padding: 32px; font-size: 12px; }
        h1 { font-size: 24px; margin: 0 0 4px 0; letter-spacing: -0.5px; }
        .muted { color: #777; }
        .row { display: flex; justify-content: space-between; align-items: flex-start; gap: 24px; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; }
        .badge-paid { background: #16a34a; color: white; }
        .badge-open { background: #f59e0b; color: white; }
        .badge-void { background: #6b7280; color: white; }
        table { width: 100%; border-collapse: collapse; margin-top: 24px; }
        th, td { text-align: left; padding: 10px 8px; border-bottom: 1px solid #e5e7eb; }
        th { background: #f9fafb; font-weight: 600; text-transform: uppercase; font-size: 10px; letter-spacing: 0.5px; color: #6b7280; }
        td.right, th.right { text-align: right; }
        .totals { margin-top: 16px; margin-left: auto; width: 280px; }
        .totals td { padding: 4px 8px; border: 0; }
        .totals td.right { text-align: right; }
        .totals .grand td { border-top: 2px solid #111; font-weight: 700; padding-top: 10px; }
        .footer { margin-top: 64px; font-size: 10px; color: #9ca3af; border-top: 1px solid #e5e7eb; padding-top: 16px; }
    </style>
</head>
<body>
    <div class="row">
        <div>
            <h1>Invoice</h1>
            <div class="muted">{{ $invoice->number }}</div>
        </div>
        <div style="text-align: right;">
            @php
                $badgeClass = match ($invoice->status) {
                    'paid' => 'badge-paid',
                    'void', 'uncollectible' => 'badge-void',
                    default => 'badge-open',
                };
            @endphp
            <span class="badge {{ $badgeClass }}">{{ $invoice->status }}</span>
            <div class="muted" style="margin-top: 8px;">
                Issued {{ optional($invoice->issued_at)->format('M j, Y') }}
            </div>
            @if ($invoice->due_at)
                <div class="muted">Due {{ $invoice->due_at->format('M j, Y') }}</div>
            @endif
        </div>
    </div>

    <div class="row" style="margin-top: 32px;">
        <div>
            <div class="muted" style="text-transform: uppercase; font-size: 10px; letter-spacing: 0.5px;">Billed to</div>
            <div style="font-weight: 600; margin-top: 4px;">{{ $tenant->name }}</div>
            <div class="muted">{{ $tenant->slug }}</div>
        </div>
        <div style="text-align: right;">
            <div class="muted" style="text-transform: uppercase; font-size: 10px; letter-spacing: 0.5px;">Gateway</div>
            <div style="font-weight: 600; margin-top: 4px;">{{ ucfirst($invoice->gateway) }}</div>
            @if ($invoice->gateway_invoice_id)
                <div class="muted">{{ $invoice->gateway_invoice_id }}</div>
            @endif
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Description</th>
                <th class="right">Amount</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($invoice->lines as $line)
                <tr>
                    <td>{{ $line->description ?? 'Line item' }}</td>
                    <td class="right">{{ number_format(($line->amount_cents ?? 0) / 100, 2) }} {{ $invoice->currency }}</td>
                </tr>
            @empty
                <tr>
                    <td>
                        @if ($invoice->subscription && $invoice->subscription->plan)
                            {{ $invoice->subscription->plan->name }} subscription
                        @else
                            Subscription charge
                        @endif
                    </td>
                    <td class="right">{{ number_format($invoice->subtotal_cents / 100, 2) }} {{ $invoice->currency }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td>Subtotal</td>
            <td class="right">{{ number_format($invoice->subtotal_cents / 100, 2) }} {{ $invoice->currency }}</td>
        </tr>
        @if ($invoice->discount_cents > 0)
            <tr>
                <td>Discount</td>
                <td class="right">−{{ number_format($invoice->discount_cents / 100, 2) }} {{ $invoice->currency }}</td>
            </tr>
        @endif
        @if ($invoice->tax_cents > 0)
            <tr>
                <td>Tax</td>
                <td class="right">{{ number_format($invoice->tax_cents / 100, 2) }} {{ $invoice->currency }}</td>
            </tr>
        @endif
        <tr class="grand">
            <td>Total</td>
            <td class="right">{{ number_format($invoice->total_cents / 100, 2) }} {{ $invoice->currency }}</td>
        </tr>
        <tr>
            <td>Paid</td>
            <td class="right">{{ number_format($invoice->amount_paid_cents / 100, 2) }} {{ $invoice->currency }}</td>
        </tr>
        <tr>
            <td>Due</td>
            <td class="right">{{ number_format($invoice->amount_due_cents / 100, 2) }} {{ $invoice->currency }}</td>
        </tr>
    </table>

    <div class="footer">
        Generated on {{ now()->format('M j, Y \a\t H:i T') }}.
        @if ($invoice->paid_at)
            Paid on {{ $invoice->paid_at->format('M j, Y') }}.
        @endif
    </div>
</body>
</html>
