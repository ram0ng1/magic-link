<x-mail::plain.information>
{{ $intro }}

{{ $code }}

@if ($magicLink)
{{ $button }}: {{ $magicLink }}

@endif
{{ $expiry }}

{{ $ignore }}
</x-mail::plain.information>
