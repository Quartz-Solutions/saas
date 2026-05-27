@component('mail::message')
# You left something in your cart

Hi {{ $user->name }},

You started signing up for **{{ $planName ?? 'a plan' }}** but didn't finish. We've cleaned up that session, but it only takes a moment to pick up where you left off.

@component('mail::button', ['url' => $resumeUrl])
Pick a plan
@endcomponent

If you ran into a problem during checkout, hit reply — we read every message.

Thanks,
The {{ config('app.name') }} team
@endcomponent
