<?php

/*
 * This file is part of ramon/magic-link.
 *
 * Copyright (c) 2026 Ramon Guilherme.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Ramon\MagicLink\Api;

use Flarum\Api\Context;
use Flarum\User\Exception\PermissionDeniedException;
use Illuminate\Support\Arr;
use Ramon\MagicLink\MagicLink;

/**
 * Sem senha, o cadastro só termina com o token de um login externo (o código
 * deste e-mail ou um provedor OAuth): o e-mail já chega confirmado. O admin
 * continua criando usuários direto.
 */
class RequireRegistrationToken
{
    public function __construct(
        protected MagicLink $magicLink
    ) {
    }

    /**
     * @throws PermissionDeniedException
     */
    public function __invoke(Context $context): void
    {
        if (! $this->magicLink->passwordless() || $context->getActor()->isAdmin()) {
            return;
        }

        if (empty(Arr::get($context->body(), 'data.attributes.token'))) {
            throw new PermissionDeniedException();
        }
    }
}
