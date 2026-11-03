<?php

/*
 * This file is part of ramon/magic-link.
 *
 * Copyright (c) 2026 Ramon Guilherme.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Ramon\MagicLink\Middleware;

use Flarum\Http\Exception\RouteNotFoundException;
use Laminas\Diactoros\Response\EmptyResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Ramon\MagicLink\MagicLink;

/**
 * Redefinir a senha entra na conta sem conferir senha nenhuma, então o modo
 * sem senha desliga as três rotas: o pedido (`forgot`) e a página e o envio
 * da nova senha, inclusive para links de redefinição emitidos antes.
 */
class RestrictPasswordRoutes implements MiddlewareInterface
{
    public function __construct(
        protected MagicLink $magicLink
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $routeName = $request->getAttribute('routeName');

        if (! in_array($routeName, ['forgot', 'resetPassword', 'savePassword'], true) || ! $this->magicLink->passwordless()) {
            return $handler->handle($request);
        }

        // A mesma resposta vazia que o core dá para um e-mail desconhecido.
        if ($routeName === 'forgot') {
            return new EmptyResponse();
        }

        throw new RouteNotFoundException();
    }
}
