<?php

/*
 * This file is part of ramon/magic-link.
 *
 * Copyright (c) 2026 Ramon Guilherme.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Ramon\MagicLink;

use Flarum\Settings\SettingsRepositoryInterface;

/**
 * As opções da extensão num lugar só.
 */
class MagicLink
{
    public const PROVIDER = 'magic-link';
    public const TICKET_PREFIX = 'magic-link:';

    public const MIN_LIFETIME = 5;
    public const MAX_LIFETIME = 1440;

    public function __construct(
        protected SettingsRepositoryInterface $settings
    ) {
    }

    /**
     * Sem senha: o fórum não aceita, não guarda e não redefine senhas.
     */
    public function passwordless(): bool
    {
        return ! $this->settings->get('ramon-magic-link.allow_password');
    }

    public function lifetimeMinutes(): int
    {
        $minutes = (int) $this->settings->get('ramon-magic-link.token_lifetime');

        return max(self::MIN_LIFETIME, min(self::MAX_LIFETIME, $minutes ?: self::MAX_LIFETIME));
    }

    public function allowsSignUp(): bool
    {
        return (bool) $this->settings->get('allow_sign_up');
    }
}
