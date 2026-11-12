<x-mail::message>
# Your workspaces

You asked for the workspaces linked to this email address. Sign in to any of them below.

@foreach ($workspaces as $workspace)
- [{{ $workspace['name'] }}]({{ $workspace['url'] }})
@endforeach

If you didn't ask for this, you can ignore this email.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
