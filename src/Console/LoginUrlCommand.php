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
use Flarum\Http\UrlGenerator;
use Flarum\User\UserRepository;
use Ramon\MagicLink\Token\MagicToken;
use Ramon\MagicLink\Token\TokenProvider;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;

/**
 * Sem senha, o e-mail é a única porta. Se o envio de e-mail quebrar, quem tem
 * acesso ao servidor gera aqui um link de entrada (vale também no modo de
 * manutenção).
 */
class LoginUrlCommand extends AbstractCommand
{
    public function __construct(
        protected TokenProvider $tokens,
        protected UserRepository $users,
        protected UrlGenerator $url
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('magic-link:login-url')
            ->setDescription('Print a one-time sign in link for a user (username or email), without sending any email.')
            ->addArgument('user', InputArgument::REQUIRED, 'Username or email');
    }

    protected function fire(): int
    {
        $identification = (string) $this->input->getArgument('user');
        $user = $this->users->findByIdentification($identification);

        if (! $user) {
            $this->error("No user found for \"$identification\".");

            return Command::FAILURE;
        }

        $issued = $this->tokens->issue(MagicToken::TYPE_SIGNIN, $user->email, $user, ['remember' => false]);

        $this->info($this->url->to('forum')->route('magic-link.confirm', ['token' => $issued->token]));

        return Command::SUCCESS;
    }
}
