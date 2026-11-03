<?php

/*
 * This file is part of ramon/magic-link.
 *
 * Copyright (c) 2026 Ramon Guilherme.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Ramon\MagicLink\Token;

use Exception;

/**
 * Token ou código inválido, expirado ou já usado. A mensagem é a mesma para
 * todos os casos: o motivo não interessa a quem tenta adivinhar.
 */
class InvalidMagicTokenException extends Exception
{
}
