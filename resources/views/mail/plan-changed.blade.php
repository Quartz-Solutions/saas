@component('mail::message')
# Your plan has been updated

Hi {{ $user->name }},

Your subscription plan has changed.

- **From:** {{ $fromPlan ?? '—' }}
- **To:** {{ $toPlan ?? '—' }}
- **Effective:** {{ $effectiveAt ?? now()->toDayDateTimeString() }}

@isset($billingUrl)
@component('mail::button', ['url' => $billingUrl])
View billing
@endcomponent
@endisset

If you did not make this change, please contact support immediately.

Thanks,
The {{ config('app.name') }} team
@endcomponent
