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

use Flarum\Foundation\AbstractServiceProvider;
use Ramon\MagicLink\Mail\MagicLinkMailer;

class RecordingMailerProvider extends AbstractServiceProvider
{
    public function register(): void
    {
        RecordingMailer::$sent = [];

        $this->container->bind(MagicLinkMailer::class, RecordingMailer::class);
    }
}
