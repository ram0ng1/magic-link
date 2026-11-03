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

use Flarum\Foundation\AbstractServiceProvider;

class MagicLinkServiceProvider extends AbstractServiceProvider
{
    public function register(): void
    {
        // Como o /login do core, as rotas de entrar continuam abertas no modo
        // de manutenção; o resto do fórum segue bloqueado para quem não é admin.
        $this->container->extend('flarum.forum.maintenance_route_exclusions', function (array $routes) {
            return array_merge($routes, ['magic-link.verify', 'magic-link.confirm', 'magic-link.confirm.submit']);
        });
    }
}
