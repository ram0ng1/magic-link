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

use Flarum\Http\Controller\AbstractHtmlController;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Arr;
use Psr\Http\Message\ServerRequestInterface;
use Ramon\MagicLink\Token\TokenProvider;

/**
 * GET /magic-link/{token}: a página com o botão de entrar. Abrir o link não
 * gasta nada; só o POST do botão entra na conta.
 */
class MagicLinkViewController extends AbstractHtmlController
{
    public function __construct(
        protected Factory $view,
        protected TokenProvider $tokens
    ) {
    }

    public function render(ServerRequestInterface $request): View
    {
        $token = $this->tokens->peekLink((string) Arr::get($request->getQueryParams(), 'token', ''));

        return $this->view
            ->make('ramon-magic-link::confirm')
            ->with('valid', $token !== null)
            ->with('type', $token === null ? 'signin' : $token->type)
            ->with('email', $token === null ? '' : $token->email)
            ->with('csrfToken', $request->getAttribute('session')->token());
    }
}
