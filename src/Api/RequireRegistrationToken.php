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
use Flarum\User\RegistrationToken;
use Illuminate\Support\Arr;
use Ramon\MagicLink\MagicLink;

/**
 * Sem senha, o cadastro só termina com o token de um login externo (o código
 * deste e-mail ou um provedor OAuth) que traga o e-mail confirmado. Um token
 * que só sugere o e-mail deixaria cadastrar o endereço de outra pessoa. O
 * admin continua criando usuários direto.
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

        $value = Arr::get($context->body(), 'data.attributes.token');
        $token = is_string($value) && $value !== '' ? RegistrationToken::query()->find($value) : null;

        if (! $token instanceof RegistrationToken || empty($token->user_attributes['email'])) {
            throw new PermissionDeniedException();
        }
    }
}
