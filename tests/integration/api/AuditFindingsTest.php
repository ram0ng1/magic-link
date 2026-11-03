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

use Carbon\Carbon;
use Flarum\Extend;
use Flarum\Http\AccessToken;
use Flarum\Http\SessionAccessToken;
use Flarum\User\Event\Activated;
use Flarum\User\RegistrationToken;
use Flarum\User\User;
use Laminas\Diactoros\ServerRequest;
use Psr\Http\Message\ResponseInterface;
use Ramon\MagicLink\Tests\integration\MagicLinkTestCase;
use Ramon\MagicLink\Tests\Support\RecordingMailer;
use Ramon\MagicLink\Token\MagicToken;

/**
 * Um teste por achado confirmado da auditoria (run-1): cada um falha no
 * código antes da correção.
 */
class AuditFindingsTest extends MagicLinkTestCase
{
    public static bool $activated = false;

    private function fromIp(string $ip, array $body): ResponseInterface
    {
        $request = new ServerRequest(['REMOTE_ADDR' => $ip], [], '/api/magic-link', 'POST');

        return $this->send($this->requestWithJsonBody($request, $body));
    }

    /** Achado 1: o 429 por IP contava só pedidos de contas existentes. */
    public function test_limite_por_ip_conta_tambem_email_desconhecido(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->assertSame(201, $this->fromIp('10.0.0.1', ['identification' => "ninguem$i@example.com"])->getStatusCode());
        }

        $this->assertSame(429, $this->fromIp('10.0.0.1', ['identification' => 'outro@example.com'])->getStatusCode());
        $this->assertSame(201, $this->fromIp('10.0.0.2', ['identification' => 'outro@example.com'])->getStatusCode());
    }

    /** Achado 2: no cadastro, nome inválido dava 422 só para e-mail sem conta. */
    public function test_cadastro_valida_o_nome_antes_de_olhar_o_email(): void
    {
        $this->setting('allow_sign_up', '1');

        $existing = $this->requestCode(['type' => 'signup', 'identification' => 'normal@machine.local', 'username' => '!!']);
        $unknown = $this->requestCode(['type' => 'signup', 'identification' => 'nova@example.com', 'username' => '!!']);

        $this->assertSame(422, $existing->getStatusCode());
        $this->assertSame(422, $unknown->getStatusCode());
        $this->assertSame([], RecordingMailer::$sent);
    }

    /** Achado 3: cadastro sem nome de usuário criava conta com nome vazio. */
    public function test_cadastro_exige_nome_de_usuario(): void
    {
        $this->setting('allow_sign_up', '1');

        $this->assertSame(422, $this->requestCode(['type' => 'signup', 'identification' => 'nova@example.com'])->getStatusCode());
        $this->assertSame([], RecordingMailer::$sent);
    }

    /** Achado 4: força bruta do código espalhada por vários pedidos. */
    public function test_novo_codigo_invalida_o_anterior(): void
    {
        $this->requestCode(['identification' => 'normal']);
        $first = $this->lastIssued();
        $this->requestCode(['identification' => 'normal']);

        $this->assertSame(422, $this->verify($first->model->ref, $first->code)->getStatusCode());
        $this->assertSame(302, $this->send($this->request('POST', '/magic-link/'.$first->token))->getStatusCode());
        $this->assertSame(0, AccessToken::query()->count());

        $second = $this->lastIssued();
        $this->assertSame(200, $this->verify($second->model->ref, $second->code)->getStatusCode());
    }

    public function test_erros_somados_entre_pedidos_bloqueiam_o_codigo(): void
    {
        for ($round = 0; $round < 3; $round++) {
            $this->requestCode(['identification' => 'normal']);
            $issued = $this->lastIssued();

            for ($i = 0; $i < 5; $i++) {
                $this->verify($issued->model->ref, $this->wrongCode($issued->code));
            }
        }

        // 15 erros no dia: nem o código certo de um pedido novo entra (o link continua valendo).
        $this->requestCode(['identification' => 'normal']);
        $issued = $this->lastIssued();

        $this->assertSame(422, $this->verify($issued->model->ref, $issued->code)->getStatusCode());
        $this->send($this->request('POST', '/magic-link/'.$issued->token));
        $this->assertSame(1, AccessToken::query()->count());
    }

    public function test_usar_o_link_invalida_o_codigo(): void
    {
        $this->requestCode(['identification' => 'normal']);
        $issued = $this->lastIssued();

        $this->send($this->request('POST', '/magic-link/'.$issued->token));

        $this->assertSame(422, $this->verify($issued->model->ref, $issued->code)->getStatusCode());
    }

    /** Achado 5: conta criada por outra pessoa com o e-mail da vítima continuava com o acesso dela. */
    public function test_primeira_confirmacao_tira_o_acesso_de_quem_criou_a_conta(): void
    {
        $this->setting('ramon-magic-link.allow_password', '1');
        $this->app();

        User::query()->whereKey(2)->update(['is_email_confirmed' => false]);
        $attacker = SessionAccessToken::generate(2);
        $attacker->save();

        $this->requestCode(['identification' => 'normal@machine.local']);
        $issued = $this->lastIssued();
        $this->verify($issued->model->ref, $issued->code);

        $user = User::query()->findOrFail(2);
        $this->assertTrue((bool) $user->is_email_confirmed);
        $this->assertSame('', $user->getAttributes()['password']);
        $this->assertNull(AccessToken::query()->find($attacker->id));
        $this->assertSame(1, AccessToken::query()->where('user_id', 2)->count());
    }

    public function test_sem_senha_o_cadastro_exige_token_com_email_confirmado(): void
    {
        $this->setting('allow_sign_up', '1');
        $this->app();

        $token = RegistrationToken::generate('github', '123', [], ['suggested' => ['email' => 'normal2@machine.local']]);
        $token->save();

        $response = $this->send($this->request('POST', '/register', [
            'json' => ['username' => 'outra', 'email' => 'vitima@example.com', 'token' => $token->token],
        ]));

        $this->assertSame(403, $response->getStatusCode());
    }

    /** Achado 6: o erro de validação do novo e-mail gastava o código de reautenticação. */
    public function test_codigo_de_troca_de_email_sobrevive_a_um_erro_de_validacao(): void
    {
        $this->prepareDatabase([User::class => [['id' => 3, 'username' => 'outra', 'email' => 'outra@machine.local', 'is_email_confirmed' => 1]]]);

        $this->requestCode(['type' => 'reauth'], 2);
        $issued = $this->lastIssued();
        $ticket = 'magic-link:'.$issued->model->ref.':'.$issued->code;

        $change = fn (string $email) => $this->send($this->request('PATCH', '/api/users/2', [
            'authenticatedAs' => 2,
            'json' => ['data' => ['type' => 'users', 'id' => '2', 'attributes' => ['email' => $email]], 'meta' => ['password' => $ticket]],
        ]))->getStatusCode();

        $this->assertSame(422, $change('outra@machine.local'));
        $this->assertSame(200, $change('novo@machine.local'));
    }

    /** Achado 7: no limite por e-mail a resposta era uma ref falsa, e o modal perdia a ref certa. */
    public function test_limite_por_email_responde_429_igual_para_qualquer_email(): void
    {
        // Um IP por e-mail: o limite por IP (10 em 10 min) não entra na conta.
        foreach (['10.0.1.1' => 'normal@machine.local', '10.0.1.2' => 'ninguem@example.com'] as $ip => $email) {
            for ($i = 0; $i < 5; $i++) {
                $this->assertSame(201, $this->fromIp($ip, ['identification' => $email])->getStatusCode());
            }

            $this->assertSame(429, $this->fromIp($ip, ['identification' => $email])->getStatusCode(), $email);
        }
    }

    /** Achado 8: confirmar o e-mail pelo código não disparava o evento Activated. */
    public function test_confirmar_pelo_codigo_dispara_activated(): void
    {
        self::$activated = false;
        $this->extend((new Extend\Event())->listen(Activated::class, fn () => self::$activated = true));
        $this->app();

        User::query()->whereKey(2)->update(['is_email_confirmed' => false]);

        $this->requestCode(['identification' => 'normal']);
        $issued = $this->lastIssued();
        $this->verify($issued->model->ref, $issued->code);

        $this->assertTrue(self::$activated);
    }

    /** Achado 9: link de conta com e-mail trocado voltava sempre para o botão. */
    public function test_link_que_falhou_nao_volta_a_mostrar_o_botao(): void
    {
        $this->requestCode(['identification' => 'normal']);
        $issued = $this->lastIssued();

        User::query()->whereKey(2)->update(['email' => 'novo@machine.local']);
        $this->send($this->request('POST', '/magic-link/'.$issued->token));

        $page = (string) $this->send($this->request('GET', '/magic-link/'.$issued->token))->getBody();
        $this->assertStringNotContainsString('method="POST"', $page);
    }

    /** Achado 10: a página do link não dizia para qual conta ele era. */
    public function test_pagina_do_link_mostra_o_email(): void
    {
        $this->requestCode(['identification' => 'normal']);

        $page = (string) $this->send($this->request('GET', '/magic-link/'.$this->lastIssued()->token))->getBody();

        $this->assertStringContainsString('normal@machine.local', $page);
    }

    public function test_tokens_vencidos_sao_apagados(): void
    {
        $this->requestCode(['identification' => 'normal']);
        MagicToken::query()->update(['created_at' => Carbon::now()->subDays(2)]);

        $this->assertSame(1, resolve(\Ramon\MagicLink\Token\TokenProvider::class)->prune());
    }
}
