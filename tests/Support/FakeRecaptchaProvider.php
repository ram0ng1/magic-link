<?php

/*
 * This file is part of ramon/magic-link.
 *
 * Copyright (c) 2026 Ramon Guilherme.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Ramon\MagicLink\Tests\Support;

use Flarum\Foundation\AbstractServiceProvider;
use FoF\ReCaptcha\Validators\RecaptchaValidator;

class FakeRecaptchaProvider extends AbstractServiceProvider
{
    public function register(): void
    {
        FakeRecaptchaValidator::$checks = 0;

        $this->container->bind(RecaptchaValidator::class, FakeRecaptchaValidator::class);
    }
}
