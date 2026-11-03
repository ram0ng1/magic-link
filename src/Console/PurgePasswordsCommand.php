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
use Flarum\User\User;
use Ramon\MagicLink\MagicLink;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;

/**
 * Apaga de uma vez as senhas guardadas antes da extensão. Sem isso, cada uma
 * só some quando a pessoa entra pelo e-mail.
 */
class PurgePasswordsCommand extends AbstractCommand
{
    public function __construct(
        protected MagicLink $magicLink
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('magic-link:purge-passwords')
            ->setDescription('Erase every stored password hash. Members keep signing in by email.')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Run even while passwords are allowed in the settings');
    }

    protected function fire(): int
    {
        if (! $this->magicLink->passwordless() && ! $this->input->getOption('force')) {
            $this->error('Passwords are allowed in the Magic Link settings. Disable them first, or pass --force.');

            return Command::FAILURE;
        }

        $count = User::query()->where('password', '!=', '')->update(['password' => '']);

        $this->info("$count password hash(es) erased.");

        return Command::SUCCESS;
    }
}
