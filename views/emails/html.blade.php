@php
    $buttonColor = $settings->get('theme_primary_color') ?: '#4d698e';
@endphp
<x-mail::html.information>
    <p>{{ $intro }}</p>

    <p style="margin: 24px 0; text-align: center;">
        <span style="display: inline-block; padding: 12px 20px; border: 1px solid #e1e1e1; border-radius: 6px; background-color: #f7f7f7; font-family: SFMono-Regular, Consolas, 'Liberation Mono', Menlo, monospace; font-size: 30px; font-weight: bold; letter-spacing: 8px; color: #222;">{{ $code }}</span>
    </p>

    @if ($magicLink)
        <p style="margin: 24px 0; text-align: center;">
            <a href="{{ $magicLink }}" style="display: inline-block; padding: 12px 24px; border-radius: 6px; background-color: {{ $buttonColor }}; color: #fff; font-weight: bold; text-decoration: none;">{{ $button }}</a>
        </p>
    @endif

    <p>{{ $expiry }}</p>
    <p style="color: #777;">{{ $ignore }}</p>
</x-mail::html.information>
