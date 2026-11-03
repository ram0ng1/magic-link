<?php

/*
 * This file is part of ramon/magic-link.
 *
 * Copyright (c) 2026 Ramon Guilherme.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Ramon\MagicLink\Console;

use Flarum\Console\AbstractCommand;
use Ramon\MagicLink\Token\TokenProvider;
use Symfony\Component\Console\Command\Command;

/**
 * Apaga os tokens vencidos. Agendado diariamente.
 */
class PruneTokensCommand extends AbstractCommand
{
    public function __construct(
        protected TokenProvider $tokens
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('magic-link:prune')
            ->setDescription('Delete expired magic link tokens.');
    }

    protected function fire(): int
    {
        $this->info($this->tokens->prune().' expired token(s) deleted.');

        return Command::SUCCESS;
    }
}
