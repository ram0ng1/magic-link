<?php

/*
 * This file is part of ramon/magic-link.
 *
 * Copyright (c) 2026 Ramon Guilherme.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Ramon\MagicLink\Auth;

use Flarum\User\User;
use Ramon\MagicLink\MagicLink;
use Ramon\MagicLink\Token\InvalidMagicTokenException;
use Ramon\MagicLink\Token\MagicToken;
use Ramon\MagicLink\Token\TokenProvider;

/**
 * Tudo que pede a senha atual (trocar e-mail, apagar a conta na fof/gdpr,
 * /api/token…) passa por User::checkPassword(). Aqui entram:
 *
 * - o bilhete "magic-link:<ref>:<código>", do código de reautenticação que foi
 *   para o e-mail da conta;
 * - no modo sem senha, `false` para qualquer outra coisa, o que encerra os
 *   outros checkers: nenhuma senha antiga vale mais.
 */
class MagicLinkPasswordChecker
{
    public function __construct(
        protected MagicLink $magicLink,
        protected TokenProvider $tokens
    ) {
    }

    public function __invoke(User $user, #[\SensitiveParameter] string $password): ?bool
    {
        if (str_starts_with($password, MagicLink::TICKET_PREFIX)) {
            return $this->checkTicket($user, substr($password, strlen(MagicLink::TICKET_PREFIX)));
        }

        return $this->magicLink->passwordless() ? false : null;
    }

    protected function checkTicket(User $user, #[\SensitiveParameter] string $ticket): bool
    {
        $parts = explode(':', $ticket, 2);

        if (count($parts) !== 2 || $user->id === null) {
            return false;
        }

        [$ref, $code] = $parts;

        try {
            $this->tokens->consumeCode($ref, $code, [MagicToken::TYPE_REAUTH], $user);
        } catch (InvalidMagicTokenException) {
            return false;
        }

        return true;
    }
}
