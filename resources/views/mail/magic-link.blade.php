@component('mail::message')
# Your sign-in link

Hi {{ $user->name }},

Click the button below to sign in. This link expires in 15 minutes and can be used only once.

@component('mail::button', ['url' => $magicUrl])
Sign in
@endcomponent

If you did not request this link, you can safely ignore this email.

Thanks,
The {{ config('app.name') }} team
@endcomponent
