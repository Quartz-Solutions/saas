@component('mail::message')
# Welcome to {{ config('app.name') }}, {{ $user->name }}!

We're glad to have you on board. Your account is ready — log in to set up your first workspace.

@component('mail::button', ['url' => $loginUrl])
Get started
@endcomponent

If you have questions, just reply to this email — we read everything.

Thanks,
The {{ config('app.name') }} team
@endcomponent
