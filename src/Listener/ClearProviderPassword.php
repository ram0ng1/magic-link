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

use Flarum\User\Event\RegisteringFromProvider;
use Ramon\MagicLink\MagicLink;

/**
 * O core preenche uma senha aleatória em todo cadastro por token. Quem se
 * cadastrou pelo e-mail não precisa dela, mesmo com a senha permitida.
 */
class ClearProviderPassword
{
    public function handle(RegisteringFromProvider $event): void
    {
        if ($event->provider === MagicLink::PROVIDER) {
            $event->user->password = '';
        }
    }
}
