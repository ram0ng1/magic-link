<?php

/*
 * This file is part of ramon/magic-link.
 *
 * Copyright (c) 2026 Ramon Guilherme.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Ramon\MagicLink\Tests\integration\forum;

use Carbon\Carbon;
use Flarum\Http\AccessToken;
use Flarum\User\LoginProvider;
use Flarum\User\User;
use Ramon\MagicLink\Tests\integration\MagicLinkTestCase;
use Ramon\MagicLink\Token\MagicToken;

/**
 * POST /magic-link/verify: o código do e-mail.
 */
class VerifyCodeTest extends MagicLinkTestCase
{
    public function test_codigo_certo_entra_uma_vez_so(): void
    {
        $this->requestCode(['identification' => 'normal@machine.local', 'remember' => true]);
        $issued = $this->lastIssued();

        $response = $this->verify($issued->model->ref, $issued->code);

        $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());
        $this->assertSame(['action' => 'login'], $this->json($response));
        $this->assertStringContainsString('flarum_remember=', implode(';', $response->getHeader('Set-Cookie')));
        $this->assertSame(1, AccessToken::query()->where('user_id', 2)->count());

        // Uso único: o mesmo código não entra de novo, e o link morre junto.
        $this->assertSame(422, $this->verify($issued->model->ref, $issued->code)->getStatusCode());
        $link = $this->send($this->request('POST', '/magic-link/'.$issued->token));
        $this->assertSame(302, $link->getStatusCode());
        $this->assertStringEndsWith('/magic-link/'.$issued->token, $link->getHeaderLine('Location'));
        $this->assertSame(0, MagicToken::query()->findOrFail($issued->model->id)->used_count);
    }

    public function test_entrar_apaga_a_senha(): void
    {
        $this->requestCode(['identification' => 'normal']);
        $issued = $this->lastIssued();

        $this->verify($issued->model->ref, $issued->code);

        $user = User::query()->findOrFail(2);
        $this->assertSame('', $user->getAttributes()['password']);
    }

    public function test_cinco_erros_bloqueiam_o_codigo(): void
    {
        $this->requestCode(['identification' => 'normal']);
        $issued = $this->lastIssued();

        for ($i = 0; $i < 5; $i++) {
            $this->assertSame(422, $this->verify($issued->model->ref, $this->wrongCode($issued->code))->getStatusCode());
        }

        $this->assertSame(422, $this->verify($issued->model->ref, $issued->code)->getStatusCode());
        $this->assertSame(0, AccessToken::query()->count());
    }

    public function test_codigo_vencido(): void
    {
        $this->requestCode(['identification' => 'normal']);
        $issued = $this->lastIssued();

        MagicToken::query()->whereKey($issued->model->id)->update(['created_at' => Carbon::now()->subMinutes(1441)]);

        $this->assertSame(422, $this->verify($issued->model->ref, $issued->code)->getStatusCode());
    }

    public function test_ref_inventada_e_codigo_de_reautenticacao_nao_entram(): void
    {
        $this->assertSame(422, $this->verify('00000000-0000-4000-8000-000000000000', '123456')->getStatusCode());

        $this->requestCode(['type' => 'reauth'], 2);
        $issued = $this->lastIssued();

        $this->assertSame(422, $this->verify($issued->model->ref, $issued->code)->getStatusCode());
    }

    public function test_email_trocado_depois_do_pedido_invalida_o_codigo(): void
    {
        $this->requestCode(['identification' => 'normal']);
        $issued = $this->lastIssued();

        User::query()->whereKey(2)->update(['email' => 'novo@machine.local']);

        $this->assertSame(422, $this->verify($issued->model->ref, $issued->code)->getStatusCode());
    }

    public function test_cadastro_termina_no_register_do_core_sem_senha(): void
    {
        $this->setting('allow_sign_up', '1');

        $this->requestCode(['type' => 'signup', 'identification' => 'nova@example.com', 'username' => 'nova']);
        $issued = $this->lastIssued();

        $verified = $this->verify($issued->model->ref, $issued->code);
        $body = $this->json($verified);

        $this->assertSame(200, $verified->getStatusCode(), (string) $verified->getBody());
        $this->assertSame('register', $body['action']);
        $this->assertSame('nova@example.com', $body['email']);
        $this->assertSame('nova', $body['username']);

        $registered = $this->send($this->request('POST', '/register', [
            'json' => ['username' => 'nova', 'email' => 'nova@example.com', 'token' => $body['token']],
        ]));

        $this->assertSame(201, $registered->getStatusCode(), (string) $registered->getBody());

        $user = User::query()->where('username', 'nova')->firstOrFail();
        $this->assertTrue((bool) $user->is_email_confirmed);
        $this->assertSame('', $user->getAttributes()['password']);
        $this->assertTrue(LoginProvider::query()->where('user_id', $user->id)->where('provider', 'magic-link')->exists());
    }
}
