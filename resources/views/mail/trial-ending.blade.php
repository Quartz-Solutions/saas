@component('mail::message')
# Your trial is ending soon

Hi {{ $user->name }},

Your trial on **{{ $planName ?? 'your plan' }}** ends in {{ $daysRemaining ?? '—' }} days, on {{ $endsAt ?? '—' }}.

To keep your workspace active, add a payment method before the trial ends.

@isset($billingUrl)
@component('mail::button', ['url' => $billingUrl])
Add payment method
@endcomponent
@endisset

If you don't want to continue, no action is needed — your access will simply pause when the trial ends.

Thanks,
The {{ config('app.name') }} team
@endcomponent
