<?php

/*
 * This file is part of ramon/magic-link.
 *
 * Copyright (c) 2026 Ramon Guilherme.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Ramon\MagicLink\Tests\unit;

use PHPUnit\Framework\TestCase;
use Ramon\MagicLink\MagicLink;
use Ramon\MagicLink\Tests\Support\ArraySettings;
use Ramon\MagicLink\Token\TokenProvider;

final class TokenProviderTest extends TestCase
{
    public function test_codigo_tem_seis_digitos_com_zeros_a_esquerda(): void
    {
        for ($i = 0; $i < 200; $i++) {
            self::assertMatchesRegularExpression('/^\d{6}$/', TokenProvider::generateCode());
        }
    }

    public function test_hash_do_codigo_depende_da_ref(): void
    {
        self::assertNotSame(TokenProvider::hashCode('a', '123456'), TokenProvider::hashCode('b', '123456'));
        self::assertSame(64, strlen(TokenProvider::hashToken('x')));
    }

    public function test_validade_fica_entre_cinco_minutos_e_um_dia(): void
    {
        $lifetime = fn (mixed $value) => (new MagicLink(new ArraySettings(['ramon-magic-link.token_lifetime' => $value])))->lifetimeMinutes();

        self::assertSame(1440, $lifetime(null));
        self::assertSame(5, $lifetime('1'));
        self::assertSame(60, $lifetime('60'));
        self::assertSame(1440, $lifetime('99999'));
    }

    public function test_sem_senha_por_padrao(): void
    {
        self::assertTrue((new MagicLink(new ArraySettings()))->passwordless());
        self::assertFalse((new MagicLink(new ArraySettings(['ramon-magic-link.allow_password' => '1'])))->passwordless());
    }
}
