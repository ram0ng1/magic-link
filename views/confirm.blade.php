@extends('flarum.forum::layouts.basic')
@inject('url', 'Flarum\Http\UrlGenerator')

@section('title', $translator->trans('ramon-magic-link.views.confirm.title'))

@section('content')
    @if ($valid)
        {{-- Só o POST entra: quem abre o link antes da pessoa (antivírus, pré-visualização) não gasta a sessão. --}}
        <form class="form" method="POST" action="">
            <input type="hidden" name="csrfToken" value="{{ $csrfToken }}">

            <p>{{ $translator->trans('ramon-magic-link.views.confirm.'.$type.'_text', ['forum' => $settings->get('forum_title')]) }}</p>

            <p class="form-group">
                <button type="submit" class="button">{{ $translator->trans('ramon-magic-link.views.confirm.'.$type.'_button') }}</button>
            </p>
        </form>
    @else
        <div class="errors">
            <ul>
                <li>{{ $translator->trans('ramon-magic-link.views.confirm.invalid_text') }}</li>
            </ul>
        </div>

        <p><a href="{{ $url->to('forum')->base() }}">{{ $translator->trans('ramon-magic-link.views.confirm.back_link') }}</a></p>
    @endif
@endsection
