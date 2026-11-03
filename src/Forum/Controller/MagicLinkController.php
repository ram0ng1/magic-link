<?php

/*
 * This file is part of ramon/magic-link.
 *
 * Copyright (c) 2026 Ramon Guilherme.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Ramon\MagicLink\Forum\Controller;

use Flarum\Http\RememberAccessToken;
use Flarum\Http\Rememberer;
use Flarum\Http\UrlGenerator;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\RedirectResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Ramon\MagicLink\Auth\MagicSignIn;
use Ramon\MagicLink\Token\InvalidMagicTokenException;
use Ramon\MagicLink\Token\TokenProvider;

/**
 * POST /magic-link/{token}: o botão da página do link. Conta existente entra
 * e volta para o fórum; e-mail novo volta com o SignUpModal aberto.
 */
class MagicLinkController implements RequestHandlerInterface
{
    public function __construct(
        protected TokenProvider $tokens,
        protected MagicSignIn $signIn,
        protected Rememberer $rememberer,
        protected UrlGenerator $url
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $token = (string) Arr::get($request->getQueryParams(), 'token', '');

        try {
            $result = $this->signIn->complete($this->tokens->consumeLink($token), $request);
        } catch (InvalidMagicTokenException) {
            // A própria página explica que o link não vale mais.
            return new RedirectResponse($this->url->to('forum')->route('magic-link.confirm', ['token' => $token]));
        }

        $response = new RedirectResponse($this->url->to('forum')->base());

        if (! $result->isLogin()) {
            MagicSignIn::rememberRegistration($request->getAttribute('session'), $result);

            return $response;
        }

        if ($result->accessToken instanceof RememberAccessToken) {
            $response = $this->rememberer->remember($response, $result->accessToken);
        }

        return $response;
    }
}
