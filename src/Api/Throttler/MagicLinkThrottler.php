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
use Ramon\MagicLink\Token\RateLimiter;

/**
 * Limite por IP dos pedidos de código. Conta todo pedido, exista a conta ou
 * não; o limite por e-mail fica no controller.
 */
class MagicLinkThrottler
{
    public const MAX_PER_IP = 10;
    public const WINDOW = 600;

    public function __construct(
        protected RateLimiter $limiter
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

        return $this->limiter->hit('ip:'.$ip, self::WINDOW) > self::MAX_PER_IP ? true : null;
    }
}
