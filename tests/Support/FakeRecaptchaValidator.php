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

use Flarum\Foundation\ValidationException;
use FoF\ReCaptcha\Validators\RecaptchaValidator;

/**
 * O validador da fof/recaptcha sem chamar o Google: só `token-ok` passa.
 */
class FakeRecaptchaValidator extends RecaptchaValidator
{
    public const VALID = 'token-ok';

    public static int $checks = 0;

    public function assertValid(array $attributes): void
    {
        self::$checks++;

        if (($attributes['g-recaptcha-response'] ?? null) !== self::VALID) {
            throw new ValidationException(['g-recaptcha-response' => 'reCAPTCHA']);
        }
    }
}
