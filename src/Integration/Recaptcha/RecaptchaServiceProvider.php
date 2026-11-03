<?php

/*
 * This file is part of ramon/magic-link.
 *
 * Copyright (c) 2026 Ramon Guilherme.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Ramon\MagicLink\Integration\Recaptcha;

use Flarum\Foundation\AbstractServiceProvider;

class RecaptchaServiceProvider extends AbstractServiceProvider
{
    public function register(): void
    {
        $this->container->extend('ramon-magic-link.request_guards', function (array $guards) {
            return array_merge($guards, [RecaptchaGuard::class]);
        });
    }
}
