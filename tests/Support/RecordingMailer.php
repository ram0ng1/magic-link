<?php

/*
 * This file is part of ramon/magic-link.
 *
 * Copyright (c) 2026 Ramon Guilherme.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Ramon\MagicLink\Tests\Support;

use Ramon\MagicLink\Mail\MagicLinkMailer;
use Ramon\MagicLink\Token\IssuedToken;

/**
 * Guarda o que iria por e-mail: o teste lê o código e o token daqui.
 */
class RecordingMailer extends MagicLinkMailer
{
    /** @var list<IssuedToken> */
    public static array $sent = [];

    public function send(IssuedToken $issued): void
    {
        self::$sent[] = $issued;
    }

    public static function last(): IssuedToken
    {
        return self::$sent[count(self::$sent) - 1];
    }
}
