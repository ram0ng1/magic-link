<?php

/*
 * This file is part of ramon/magic-link.
 *
 * Copyright (c) 2026 Ramon Guilherme.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Ramon\MagicLink\Tests\integration;

use Flarum\Extend;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;
use Psr\Http\Message\ResponseInterface;
use Ramon\MagicLink\Tests\Support\RecordingMailer;
use Ramon\MagicLink\Tests\Support\RecordingMailerProvider;
use Ramon\MagicLink\Token\IssuedToken;

abstract class MagicLinkTestCase extends TestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('ramon-magic-link');

        // As rotas do fórum pedem CSRF; os testes do core isentam o /login do mesmo jeito.
        $this->extend(
            (new Extend\Csrf())
                ->exemptRoute('magic-link.request')
                ->exemptRoute('magic-link.verify')
                ->exemptRoute('magic-link.confirm.submit')
                ->exemptRoute('register')
                ->exemptRoute('forgot')
                ->exemptRoute('users.create')
                ->exemptRoute('login'),
            (new Extend\ServiceProvider())->register(RecordingMailerProvider::class)
        );

        $this->prepareDatabase([
            User::class => [$this->normalUser()],
        ]);
    }

    /**
     * @param array<string, mixed> $body
     */
    protected function requestCode(array $body, ?int $as = null): ResponseInterface
    {
        $options = ['json' => $body];

        if ($as !== null) {
            $options['authenticatedAs'] = $as;
        }

        return $this->send($this->request('POST', '/api/magic-link', $options));
    }

    protected function lastIssued(): IssuedToken
    {
        $this->assertNotEmpty(RecordingMailer::$sent, 'Nenhum e-mail foi enviado.');

        return RecordingMailer::last();
    }

    protected function verify(string $ref, string $code): ResponseInterface
    {
        return $this->send($this->request('POST', '/magic-link/verify', ['json' => ['ref' => $ref, 'code' => $code]]));
    }

    /** @return array<string, mixed> */
    protected function json(ResponseInterface $response): array
    {
        return json_decode((string) $response->getBody(), true) ?? [];
    }

    protected function wrongCode(string $code): string
    {
        return str_pad((string) (((int) $code + 1) % 1000000), 6, '0', STR_PAD_LEFT);
    }
}
