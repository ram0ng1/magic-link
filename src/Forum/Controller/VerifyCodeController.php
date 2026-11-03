<?php

/*
 * This file is part of ramon/magic-link.
 *
 * Copyright (c) 2026 Ramon Guilherme.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Ramon\MagicLink\Forum\Controller;

use Flarum\Http\RememberAccessToken;
use Flarum\Http\Rememberer;
use Flarum\Locale\TranslatorInterface;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Ramon\MagicLink\Auth\MagicSignIn;
use Ramon\MagicLink\Token\InvalidMagicTokenException;
use Ramon\MagicLink\Token\MagicToken;
use Ramon\MagicLink\Token\TokenProvider;

/**
 * POST /magic-link/verify: confere o código digitado no modal. É rota do
 * fórum, como o /login do core, porque precisa da sessão.
 */
class VerifyCodeController implements RequestHandlerInterface
{
    public function __construct(
        protected TokenProvider $tokens,
        protected MagicSignIn $signIn,
        protected Rememberer $rememberer,
        protected TranslatorInterface $translator
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $body = (array) $request->getParsedBody();
        $ref = (string) Arr::get($body, 'ref', '');
        $code = (string) Arr::get($body, 'code', '');

        try {
            $token = $this->tokens->consumeCode($ref, $code, [MagicToken::TYPE_SIGNIN, MagicToken::TYPE_SIGNUP]);
            $result = $this->signIn->complete($token, $request);
        } catch (InvalidMagicTokenException) {
            return $this->invalid();
        }

        $response = new JsonResponse($result->toArray());

        if ($result->accessToken instanceof RememberAccessToken) {
            $response = $this->rememberer->remember($response, $result->accessToken);
        }

        return $response;
    }

    /**
     * No formato de erro da API: o FormModal mostra a mensagem e seleciona o campo.
     */
    protected function invalid(): ResponseInterface
    {
        return new JsonResponse([
            'errors' => [[
                'status' => '422',
                'code' => 'validation_error',
                'detail' => $this->translator->trans('ramon-magic-link.forum.code.invalid_message'),
                'source' => ['pointer' => '/data/attributes/code'],
            ]],
        ], 422);
    }
}
