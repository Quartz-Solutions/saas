@component('mail::message')
# We couldn't process your payment

Hi {{ $user->name }},

Your scheduled payment for **{{ $planName ?? 'your subscription' }}** could not be completed.

- **Amount:** {{ $amount ?? '—' }}
- **Reason:** {{ $reason ?? 'The card was declined.' }}
- **Next retry:** {{ $nextRetryAt ?? 'within 24 hours' }}

Please update your payment method to avoid any interruption to your service.

@isset($billingUrl)
@component('mail::button', ['url' => $billingUrl])
Update payment method
@endcomponent
@endisset

Thanks,
The {{ config('app.name') }} team
@endcomponent
