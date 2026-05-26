@component('mail::message')
# A recovery code was used on your account

Hi {{ $user->name }},

A two-factor authentication recovery code was just used to sign in to your account.

- **When:** {{ $when }}
- **IP address:** {{ $ip }}
- **Remaining recovery codes:** {{ $remaining }}

If this was you, no action is needed. If not, please reset your password and regenerate your recovery codes immediately.

@component('mail::button', ['url' => $securityUrl])
Review security settings
@endcomponent

Thanks,
The {{ config('app.name') }} team
@endcomponent
