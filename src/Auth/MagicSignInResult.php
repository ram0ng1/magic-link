<?php

/*
 * This file is part of ramon/magic-link.
 *
 * Copyright (c) 2026 Ramon Guilherme.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Ramon\MagicLink\Auth;

use Flarum\Http\AccessToken;
use Flarum\User\RegistrationToken;
use Flarum\User\User;

final readonly class MagicSignInResult
{
    private function __construct(
        public string $action,
        public ?User $user = null,
        public ?AccessToken $accessToken = null,
        public ?RegistrationToken $registration = null,
        public string $email = '',
        public string $username = '',
    ) {
    }

    public static function loggedIn(User $user, AccessToken $accessToken): self
    {
        return new self('login', user: $user, accessToken: $accessToken);
    }

    public static function register(RegistrationToken $registration, string $email, string $username): self
    {
        return new self('register', registration: $registration, email: $email, username: $username);
    }

    public function isLogin(): bool
    {
        return $this->action === 'login';
    }

    /** @return array<string, string> */
    public function toArray(): array
    {
        if ($this->isLogin()) {
            return ['action' => 'login'];
        }

        return [
            'action' => 'register',
            'token' => (string) $this->registration?->token,
            'email' => $this->email,
            'username' => $this->username,
        ];
    }
}
