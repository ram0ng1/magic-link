<?php

/*
 * This file is part of ramon/magic-link.
 *
 * Copyright (c) 2026 Ramon Guilherme.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Ramon\MagicLink\Forum;

use Flarum\Frontend\Document;
use Illuminate\Contracts\Session\Session;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Leva para o fórum, uma vez só, o cadastro começado pelo link do e-mail:
 * o JS abre o SignUpModal com o token e o e-mail já confirmado.
 */
class RegistrationPayload
{
    public function __invoke(Document $document, ServerRequestInterface $request): void
    {
        $session = $request->getAttribute('session');

        if (! $session instanceof Session || ! $session->has('magic-link.registration')) {
            return;
        }

        $document->payload['magicLinkRegistration'] = $session->remove('magic-link.registration');
    }
}
