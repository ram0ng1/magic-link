<?php

/*
 * This file is part of ramon/magic-link.
 *
 * Copyright (c) 2026 Ramon Guilherme.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Ramon\MagicLink\Token;

/**
 * O que só existe no momento da emissão: o token do link e o código vão para o
 * e-mail e nunca mais podem ser lidos do banco.
 */
final readonly class IssuedToken
{
    public function __construct(
        public MagicToken $model,
        #[\SensitiveParameter] public string $token,
        #[\SensitiveParameter] public string $code,
    ) {
    }
}
