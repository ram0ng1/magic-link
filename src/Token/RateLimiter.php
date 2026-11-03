<?php

/*
 * This file is part of ramon/magic-link.
 *
 * Copyright (c) 2026 Ramon Guilherme.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Ramon\MagicLink\Token;

use Illuminate\Contracts\Cache\Repository;

/**
 * Contadores de janela fixa no cache do Flarum.
 *
 * Contam o pedido, não o que ele gerou: um e-mail sem conta gasta o limite
 * igual a um com conta, então estourar o limite não revela quem tem conta.
 * O increment é atômico nos drivers de cache comuns (redis, memcached, banco).
 */
class RateLimiter
{
    public function __construct(
        protected Repository $cache
    ) {
    }

    public function hit(string $key, int $seconds): int
    {
        $key = self::key($key);

        $this->cache->add($key, 0, $seconds);

        return (int) $this->cache->increment($key);
    }

    public function count(string $key): int
    {
        return (int) $this->cache->get(self::key($key), 0);
    }

    protected static function key(string $key): string
    {
        return 'ramon-magic-link.'.hash('sha256', $key);
    }
}
