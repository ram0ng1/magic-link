<?php

/*
 * This file is part of ramon/magic-link.
 *
 * Copyright (c) 2026 Ramon Guilherme.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Ramon\MagicLink\Tests\integration\api;

use Flarum\Extend;
use Flarum\User\User;
use Ramon\MagicLink\Tests\integration\MagicLinkTestCase;
use Ramon\MagicLink\Tests\Support\FakeRecaptchaProvider;
use Ramon\MagicLink\Tests\Support\FakeRecaptchaValidator;
use Ramon\MagicLink\Tests\Support\RecordingMailer;

/**
 * Com a fof/recaptcha ativa, pedir o código pede o captcha (o Google é trocado
 * por um validador falso).
 */
class RecaptchaTest extends MagicLinkTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('fof-recaptcha');
        $this->extend((new Extend\ServiceProvider())->register(FakeRecaptchaProvider::class));

        $this->setting('fof-recaptcha.credentials.site', 'site-key');
        $this->setting('fof-recaptcha.credentials.secret', 'secret-key');
        $this->setting('fof-recaptcha.signin', '1');
        $this->setting('fof-recaptcha.signup', '1');
        $this->setting('allow_sign_up', '1');
    }

    public function test_login_sem_captcha_nao_envia(): void
    {
        $response = $this->requestCode(['identification' => 'normal@machine.local']);

        $this->assertSame(422, $response->getStatusCode());
        // A mensagem da fof (nos testes o catálogo em cache pode não ter a tradução dela).
        $this->assertMatchesRegularExpression('/reCAPTCHA|fof-recaptcha\.lib\.not_completed/', (string) $response->getBody());
        $this->assertSame([], RecordingMailer::$sent);
    }

    public function test_login_com_captcha_envia(): void
    {
        $response = $this->requestCode(['identification' => 'normal@machine.local', 'g-recaptcha-response' => FakeRecaptchaValidator::VALID]);

        $this->assertSame(201, $response->getStatusCode(), (string) $response->getBody());
        $this->assertCount(1, RecordingMailer::$sent);
    }

    public function test_email_desconhecido_tambem_pede_captcha(): void
    {
        // Senão o captcha só seria pedido para contas que existem.
        $this->assertSame(422, $this->requestCode(['identification' => 'ninguem@example.com'])->getStatusCode());
    }

    public function test_opcao_de_login_desligada_nao_pede(): void
    {
        $this->setting('fof-recaptcha.signin', '0');

        $this->assertSame(201, $this->requestCode(['identification' => 'normal@machine.local'])->getStatusCode());
    }

    public function test_cadastro_pede_o_captcha_no_codigo_e_no_register(): void
    {
        $this->assertSame(422, $this->requestCode(['type' => 'signup', 'identification' => 'nova@example.com', 'username' => 'nova'])->getStatusCode());

        $this->requestCode(['type' => 'signup', 'identification' => 'nova@example.com', 'username' => 'nova', 'g-recaptcha-response' => FakeRecaptchaValidator::VALID]);
        $issued = $this->lastIssued();
        $token = $this->json($this->verify($issued->model->ref, $issued->code))['token'];

        $register = fn (array $extra) => $this->send($this->request('POST', '/register', [
            'json' => ['username' => 'nova', 'email' => 'nova@example.com', 'token' => $token] + $extra,
        ]));

        // O /register continua com o captcha da própria fof.
        $this->assertSame(422, $register([])->getStatusCode());
        $this->assertSame(201, $register(['g-recaptcha-response' => FakeRecaptchaValidator::VALID])->getStatusCode());
        $this->assertTrue(User::query()->where('username', 'nova')->exists());
    }

    public function test_reautenticacao_nao_pede_captcha(): void
    {
        $this->assertSame(201, $this->requestCode(['type' => 'reauth'], 2)->getStatusCode());
        $this->assertSame(0, FakeRecaptchaValidator::$checks);
    }

    public function test_sem_chaves_configuradas_nao_pede(): void
    {
        $this->setting('fof-recaptcha.credentials.secret', '');

        $this->assertSame(201, $this->requestCode(['identification' => 'normal@machine.local'])->getStatusCode());
    }
}
