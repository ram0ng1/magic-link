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

use Flarum\User\EmailToken;
use Flarum\User\PasswordToken;
use Flarum\User\User;
use Ramon\MagicLink\Tests\integration\MagicLinkTestCase;

/**
 * O modo sem senha: nenhuma senha entra, é guardada ou redefinida, e o que
 * pedia a senha atual aceita o código por e-mail.
 */
class PasswordlessTest extends MagicLinkTestCase
{
    private function logIn(): int
    {
        return $this->send($this->request('POST', '/api/token', [
            'json' => ['identification' => 'normal', 'password' => 'too-obscure'],
        ]))->getStatusCode();
    }

    public function test_senha_antiga_nao_entra(): void
    {
        $this->assertSame(401, $this->logIn());
    }

    public function test_com_a_senha_permitida_ela_volta_a_valer(): void
    {
        $this->setting('ramon-magic-link.allow_password', '1');

        $this->assertSame(200, $this->logIn());
    }

    public function test_esqueci_a_senha_nao_gera_token_e_o_reset_some(): void
    {
        $forgot = $this->send($this->request('POST', '/api/forgot', ['json' => ['email' => 'normal@machine.local']]));

        $this->assertSame(204, $forgot->getStatusCode());
        $this->assertSame(0, PasswordToken::query()->count());

        $this->assertSame(404, $this->send($this->request('GET', '/reset/qualquer'))->getStatusCode());
    }

    public function test_nenhuma_senha_chega_ao_banco(): void
    {
        $response = $this->send($this->request('PATCH', '/api/users/2', [
            'authenticatedAs' => 1,
            'json' => ['data' => ['type' => 'users', 'id' => '2', 'attributes' => ['password' => 'uma-senha-nova']]],
        ]));

        $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());
        $this->assertSame('', User::query()->findOrFail(2)->getAttributes()['password']);
    }

    public function test_cadastro_sem_token_e_negado(): void
    {
        $this->setting('allow_sign_up', '1');

        $response = $this->send($this->request('POST', '/api/users', [
            'json' => ['data' => ['type' => 'users', 'attributes' => ['username' => 'nova', 'email' => 'nova@example.com', 'password' => 'senha-forte-123']]],
        ]));

        $this->assertSame(403, $response->getStatusCode());
    }

    private function changeEmail(string $password): int
    {
        return $this->send($this->request('PATCH', '/api/users/2', [
            'authenticatedAs' => 2,
            'json' => [
                'data' => ['type' => 'users', 'id' => '2', 'attributes' => ['email' => 'novo@machine.local']],
                'meta' => ['password' => $password],
            ],
        ]))->getStatusCode();
    }

    public function test_trocar_email_com_o_codigo_de_reautenticacao(): void
    {
        $this->assertSame(401, $this->changeEmail('too-obscure'));

        $this->requestCode(['type' => 'reauth'], 2);
        $issued = $this->lastIssued();

        $this->assertSame(401, $this->changeEmail('magic-link:'.$issued->model->ref.':'.$this->wrongCode($issued->code)));
        $this->assertSame(200, $this->changeEmail('magic-link:'.$issued->model->ref.':'.$issued->code));

        // Uso único (o throttle de troca de e-mail do core contaria o token da troca anterior).
        EmailToken::query()->delete();
        $this->assertSame(401, $this->changeEmail('magic-link:'.$issued->model->ref.':'.$issued->code));
    }

    public function test_codigo_de_outra_conta_nao_serve(): void
    {
        $this->requestCode(['type' => 'reauth'], 1);
        $issued = $this->lastIssued();

        $this->assertSame(401, $this->changeEmail('magic-link:'.$issued->model->ref.':'.$issued->code));
    }
}
