@component('mail::message')
# Payment receipt

Hi {{ $user->name }},

Thanks for your payment. Here's a copy of your receipt.

@component('mail::table')
| Item | Amount |
|:-----|-------:|
| {{ $description ?? 'Subscription payment' }} | {{ $amount ?? '—' }} |
@endcomponent

- **Invoice:** {{ $invoiceNumber ?? '—' }}
- **Paid on:** {{ $paidAt ?? now()->toDayDateTimeString() }}

@isset($invoiceUrl)
@component('mail::button', ['url' => $invoiceUrl])
View invoice
@endcomponent
@endisset

Thanks for your business,
The {{ config('app.name') }} team
@endcomponent
