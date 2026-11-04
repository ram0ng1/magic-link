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

use Flarum\Locale\TranslatorInterface;
use Flarum\Queue\AbstractJob;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Contracts\View\Factory;
use Illuminate\Mail\Message;

/**
 * O SendInformationalEmailJob do core só leva um texto corrido; aqui o código
 * e o botão do link têm lugar próprio no layout de e-mail do Flarum.
 */
class SendMagicLinkEmailJob extends AbstractJob
{
    public function __construct(
        private readonly string $email,
        private readonly string $displayName,
        private readonly string $subject,
        private readonly string $title,
        private readonly string $intro,
        private readonly string $sealedCode,
        private readonly ?string $sealedLink,
        private readonly string $button,
        private readonly string $expiry,
        private readonly string $ignore,
        private readonly string $forumTitle,
        private readonly ?string $locale = null,
    ) {
        parent::__construct();
    }

    public function handle(Mailer $mailer, Factory $view, TranslatorInterface $translator, SettingsRepositoryInterface $settings): void
    {
        $code = QueueSeal::open($settings, $this->sealedCode);
        $link = $this->sealedLink !== null ? QueueSeal::open($settings, $this->sealedLink) : null;

        if ($this->locale !== null) {
            $translator->setLocale($this->locale);
        }

        // As mesmas variáveis que o layout de e-mail informativo do core lê.
        $view->share([
            'forumTitle' => $this->forumTitle,
            'userEmail' => $this->email,
            'title' => $this->title,
            'username' => $this->displayName,
        ]);

        $mailer->send(
            [
                'html' => 'ramon-magic-link::emails.html',
                'text' => 'ramon-magic-link::emails.plain',
            ],
            [
                'intro' => $this->intro,
                'code' => $code,
                'magicLink' => $link,
                'button' => $this->button,
                'expiry' => $this->expiry,
                'ignore' => $this->ignore,
            ],
            function (Message $message) {
                $message->to($this->email);
                $message->subject($this->subject);
            }
        );
    }
}
