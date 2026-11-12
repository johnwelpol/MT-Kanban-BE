<x-mail::message>
# You're invited to {{ $workspaceName }}

{{ $inviterName }} invited you to join **{{ $workspaceName }}** as {{ $role->value }}.

<x-mail::button :url="$url">
Accept invitation
</x-mail::button>

This link expires in 7 days. If you weren't expecting this, you can ignore this email.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
