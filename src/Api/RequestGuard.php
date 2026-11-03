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

use Flarum\User\User;

/**
 * Uma checagem a mais antes de mandar o código (o reCAPTCHA da fof/recaptcha,
 * por exemplo). As guardas ficam em `ramon-magic-link.request_guards`.
 */
interface RequestGuard
{
    /**
     * @param array<string, mixed> $body
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function check(string $type, array $body, User $actor): void;
}
