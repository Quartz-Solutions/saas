@component('mail::message')
# Verify your email address

Hi {{ $user->name }},

Please confirm your email address by clicking the button below. This link expires in 60 minutes.

@component('mail::button', ['url' => $verifyUrl])
Verify email
@endcomponent

If you did not create an account, no further action is required.

Thanks,
The {{ config('app.name') }} team
@endcomponent
