<?php

/*
 * This file is part of ramon/magic-link.
 *
 * Copyright (c) 2026 Ramon Guilherme.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Ramon\MagicLink\Api\Throttler;

use Psr\Http\Message\ServerRequestInterface;
use Ramon\MagicLink\Token\TokenProvider;

/**
 * Limite por IP dos pedidos de código. O limite por e-mail fica no controller,
 * que finge o envio em vez de responder 429 (o 429 revelaria a conta).
 */
class MagicLinkThrottler
{
    public const MAX_PER_IP = 10;
    public const WINDOW = 600;

    public function __construct(
        protected TokenProvider $tokens
    ) {
    }

    public function __invoke(ServerRequestInterface $request): ?bool
    {
        if ($request->getAttribute('routeName') !== 'magic-link.request') {
            return null;
        }

        $ip = $request->getAttribute('ipAddress');

        if (! is_string($ip) || $ip === '') {
            return null;
        }

        return $this->tokens->recentCount('ip_address', $ip, self::WINDOW) >= self::MAX_PER_IP ? true : null;
    }
}
