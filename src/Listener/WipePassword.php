<?php

/*
 * This file is part of ramon/magic-link.
 *
 * Copyright (c) 2026 Ramon Guilherme.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Ramon\MagicLink\Listener;

use Flarum\User\User;
use Ramon\MagicLink\MagicLink;

/**
 * No modo sem senha nenhuma senha chega ao banco, venha de onde vier (cadastro
 * pela API, admin criando usuário, outra extensão): sem senha, não há o que vazar.
 */
class WipePassword
{
    public function __construct(
        protected MagicLink $magicLink
    ) {
    }

    public function __invoke(User $user): void
    {
        if (! $this->magicLink->passwordless() || ! $user->isDirty('password')) {
            return;
        }

        if ($user->getAttributes()['password'] ?? '') {
            $user->password = '';
        }
    }
}
