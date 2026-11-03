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

use Ramon\MagicLink\Tests\integration\MagicLinkTestCase;
use Ramon\MagicLink\Tests\Support\RecordingMailer;
use Ramon\MagicLink\Token\MagicToken;

/**
 * POST /api/magic-link: o que é enviado e o que a resposta deixa (não deixa) saber.
 */
class RequestMagicLinkTest extends MagicLinkTestCase
{
    public function test_login_com_email_existente_envia_codigo(): void
    {
        $response = $this->requestCode(['type' => 'signin', 'identification' => 'normal@machine.local', 'remember' => true]);

        $this->assertSame(201, $response->getStatusCode(), (string) $response->getBody());

        $issued = $this->lastIssued();
        $this->assertSame($this->json($response)['ref'], $issued->model->ref);
        $this->assertSame(MagicToken::TYPE_SIGNIN, $issued->model->type);
        $this->assertSame(2, $issued->model->user_id);
        $this->assertTrue($issued->model->dataValue('remember'));
        $this->assertMatchesRegularExpression('/^\d{6}$/', $issued->code);
        // Só os hashes ficam no banco.
        $this->assertNotSame($issued->token, $issued->model->token_hash);
        $this->assertStringNotContainsString($issued->code, (string) json_encode(MagicToken::query()->first()?->toArray()));
    }

    public function test_login_pelo_nome_de_usuario_envia_para_o_email_da_conta(): void
    {
        $this->requestCode(['identification' => 'normal']);

        $this->assertSame('normal@machine.local', $this->lastIssued()->model->email);
    }

    public function test_email_desconhecido_responde_igual_e_nao_envia(): void
    {
        $response = $this->requestCode(['type' => 'signin', 'identification' => 'ninguem@example.com']);

        $this->assertSame(201, $response->getStatusCode());
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $this->json($response)['ref']);
        $this->assertSame([], RecordingMailer::$sent);
        $this->assertSame(0, MagicToken::query()->count());
    }

    public function test_honeypot_finge_o_envio(): void
    {
        $response = $this->requestCode(['identification' => 'normal@machine.local', 'honeypot' => 'http://spam']);

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame([], RecordingMailer::$sent);
    }

    public function test_cadastro_guarda_o_nome_de_usuario(): void
    {
        $this->setting('allow_sign_up', '1');

        $response = $this->requestCode(['type' => 'signup', 'identification' => 'Nova@Example.com', 'username' => 'nova']);

        $this->assertSame(201, $response->getStatusCode(), (string) $response->getBody());

        $model = $this->lastIssued()->model;
        $this->assertSame(MagicToken::TYPE_SIGNUP, $model->type);
        $this->assertSame('nova@example.com', $model->email);
        $this->assertNull($model->user_id);
        $this->assertSame('nova', $model->dataValue('username'));
    }

    public function test_cadastro_com_email_existente_envia_codigo_de_login(): void
    {
        $this->setting('allow_sign_up', '1');

        $response = $this->requestCode(['type' => 'signup', 'identification' => 'normal@machine.local', 'username' => 'outro']);

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame(MagicToken::TYPE_SIGNIN, $this->lastIssued()->model->type);
        $this->assertSame(2, $this->lastIssued()->model->user_id);
    }

    public function test_cadastro_com_nome_em_uso_falha_antes_de_enviar(): void
    {
        $this->setting('allow_sign_up', '1');

        $response = $this->requestCode(['type' => 'signup', 'identification' => 'nova@example.com', 'username' => 'normal']);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame([], RecordingMailer::$sent);
    }

    public function test_cadastro_fechado(): void
    {
        $this->setting('allow_sign_up', '0');

        $response = $this->requestCode(['type' => 'signup', 'identification' => 'nova@example.com', 'username' => 'nova']);

        $this->assertSame(403, $response->getStatusCode());
    }

    public function test_limite_por_email(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->assertSame(201, $this->requestCode(['identification' => 'normal@machine.local'])->getStatusCode());
        }

        $this->assertSame(429, $this->requestCode(['identification' => 'normal@machine.local'])->getStatusCode());
        $this->assertCount(5, RecordingMailer::$sent);
    }

    public function test_reautenticacao_exige_login_e_usa_o_email_da_conta(): void
    {
        $this->assertSame(401, $this->requestCode(['type' => 'reauth'])->getStatusCode());

        $response = $this->requestCode(['type' => 'reauth', 'identification' => 'outro@example.com'], 2);

        $this->assertSame(201, $response->getStatusCode(), (string) $response->getBody());
        $this->assertSame('normal@machine.local', $this->lastIssued()->model->email);
        $this->assertSame(MagicToken::TYPE_REAUTH, $this->lastIssued()->model->type);
    }

    public function test_mensagem_de_erro_traz_o_nome_do_campo(): void
    {
        $this->setting('allow_sign_up', '1');

        $response = $this->requestCode(['type' => 'signup', 'identification' => '']);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringNotContainsString(':attribute', (string) $response->getBody());
    }

    public function test_tipo_invalido(): void
    {
        $this->assertSame(422, $this->requestCode(['type' => 'admin', 'identification' => 'normal'])->getStatusCode());
    }

    public function test_atributos_do_forum(): void
    {
        $forum = $this->json($this->send($this->request('GET', '/api')));

        $this->assertTrue($forum['data']['attributes']['magicLinkPasswordless']);
        $this->assertSame(6, $forum['data']['attributes']['magicLinkCodeLength']);
    }
}
