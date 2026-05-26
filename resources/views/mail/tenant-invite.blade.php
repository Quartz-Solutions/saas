@component('mail::message')
# You're invited to join {{ $tenantName }}

{{ $inviterName }} has invited you to collaborate on **{{ $tenantName }}** on {{ config('app.name') }} as a **{{ $role }}**.

@component('mail::button', ['url' => $acceptUrl])
Accept invitation
@endcomponent

This invitation will expire on {{ $expiresAt }}.

If you don't recognize this invitation, you can safely ignore this email.

Thanks,
The {{ config('app.name') }} team
@endcomponent
