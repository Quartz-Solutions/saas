@component('mail::message')
# New sign-in to your account

Hi {{ $user->name }},

We noticed a sign-in to your account from a new device.

- **When:** {{ $when ?? now()->toDayDateTimeString() }}
- **IP address:** {{ $ip ?? '—' }}
- **Device:** {{ $userAgent ?? '—' }}
- **Approximate location:** {{ $location ?? 'Unknown' }}

If this was you, no action is needed.

If you don't recognize this sign-in, please reset your password immediately.

@isset($securityUrl)
@component('mail::button', ['url' => $securityUrl])
Review security settings
@endcomponent
@endisset

Thanks,
The {{ config('app.name') }} team
@endcomponent
