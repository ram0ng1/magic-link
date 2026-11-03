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
use Ramon\MagicLink\Tests\integration\MagicLinkTestCase;
use Ramon\MagicLink\Token\MagicToken;

/**
 * GET e POST /magic-link/{token}: o link do e-mail.
 */
class MagicLinkTest extends MagicLinkTestCase
{
    public function test_abrir_o_link_so_mostra_o_botao(): void
    {
        $this->requestCode(['identification' => 'normal']);
        $issued = $this->lastIssued();

        $page = $this->send($this->request('GET', '/magic-link/'.$issued->token));

        $this->assertSame(200, $page->getStatusCode());
        $this->assertStringContainsString('method="POST"', (string) $page->getBody());
        $this->assertSame(0, MagicToken::query()->findOrFail($issued->model->id)->used_count);
        $this->assertSame(0, AccessToken::query()->count());
    }

    public function test_link_invalido_explica(): void
    {
        $page = $this->send($this->request('GET', '/magic-link/abcdefghijklmnopqrstuvwxyz0123456789ABCD'));

        $this->assertSame(200, $page->getStatusCode());
        $this->assertStringNotContainsString('method="POST"', (string) $page->getBody());
    }

    public function test_botao_entra_e_volta_para_o_forum(): void
    {
        $this->requestCode(['identification' => 'normal']);
        $issued = $this->lastIssued();

        $response = $this->send($this->request('POST', '/magic-link/'.$issued->token));

        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringEndsWith('/', rtrim($response->getHeaderLine('Location'), '/').'/');
        $this->assertStringNotContainsString('magic-link', $response->getHeaderLine('Location'));
        $this->assertSame(1, AccessToken::query()->where('user_id', 2)->count());
        $this->assertSame(1, MagicToken::query()->findOrFail($issued->model->id)->used_count);
    }

    public function test_link_aceita_ate_sete_usos(): void
    {
        $this->requestCode(['identification' => 'normal']);
        $issued = $this->lastIssued();

        for ($i = 0; $i < 7; $i++) {
            $this->send($this->request('POST', '/magic-link/'.$issued->token));
        }

        $this->assertSame(7, AccessToken::query()->count());

        $this->send($this->request('POST', '/magic-link/'.$issued->token));
        $this->assertSame(7, AccessToken::query()->count());
    }

    public function test_link_vence_dez_minutos_depois_do_primeiro_uso(): void
    {
        $this->requestCode(['identification' => 'normal']);
        $issued = $this->lastIssued();

        $this->send($this->request('POST', '/magic-link/'.$issued->token));
        MagicToken::query()->whereKey($issued->model->id)->update(['first_used_at' => Carbon::now()->subMinutes(11)]);
        $this->send($this->request('POST', '/magic-link/'.$issued->token));

        $this->assertSame(1, AccessToken::query()->count());
    }

    public function test_link_de_cadastro_abre_o_signup_com_o_token_da_sessao(): void
    {
        $this->setting('allow_sign_up', '1');

        $this->requestCode(['type' => 'signup', 'identification' => 'nova@example.com', 'username' => 'nova']);
        $issued = $this->lastIssued();

        $response = $this->send($this->request('POST', '/magic-link/'.$issued->token));

        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringNotContainsString('token', $response->getHeaderLine('Location'));

        $forum = (string) $this->send($this->request('GET', '/', ['cookiesFrom' => $response]))->getBody();

        $this->assertStringContainsString('magicLinkRegistration', $forum);
        $this->assertStringContainsString('nova@example.com', $forum);
    }
}
