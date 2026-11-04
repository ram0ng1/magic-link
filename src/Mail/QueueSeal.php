<?php

/*
 * This file is part of ramon/magic-link.
 *
 * Copyright (c) 2026 Ramon Guilherme.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Ramon\MagicLink\Mail;

use Flarum\Settings\SettingsRepositoryInterface;

/**
 * Cifra código e link antes de irem para a fila (Redis), para que não fiquem
 * em texto legível enquanto o job espera. A chave fica nas configurações do
 * Flarum, fora do Redis.
 */
class QueueSeal
{
    private const SETTING = 'ramon-magic-link.queue_key';

    public static function seal(SettingsRepositoryInterface $settings, string $plain): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        return base64_encode($nonce.sodium_crypto_secretbox($plain, $nonce, self::key($settings)));
    }

    public static function open(SettingsRepositoryInterface $settings, string $sealed): string
    {
        $raw = base64_decode($sealed, true);
        $nonceLen = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;
        $plain = $raw === false || strlen($raw) <= $nonceLen
            ? false
            : sodium_crypto_secretbox_open(substr($raw, $nonceLen), substr($raw, 0, $nonceLen), self::key($settings));

        if ($plain === false) {
            throw new \RuntimeException('Não foi possível abrir o payload cifrado do e-mail de acesso.');
        }

        return $plain;
    }

    private static function key(SettingsRepositoryInterface $settings): string
    {
        $stored = $settings->get(self::SETTING);

        if (is_string($stored) && strlen($stored) === 64 && ctype_xdigit($stored)) {
            return hex2bin($stored);
        }

        $key = random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
        $settings->set(self::SETTING, bin2hex($key));

        return $key;
    }
}
