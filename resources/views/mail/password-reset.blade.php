@component('mail::message')
# Reset your password

Hi {{ $user->name }},

You're receiving this email because we received a password reset request for your account.

@component('mail::button', ['url' => $resetUrl])
Reset password
@endcomponent

This password reset link will expire in 60 minutes.

If you did not request a password reset, no further action is required.

Thanks,
The {{ config('app.name') }} team
@endcomponent
