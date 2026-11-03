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

use Flarum\Http\UrlGenerator;
use Flarum\Locale\TranslatorInterface;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Contracts\Queue\Queue;
use Ramon\MagicLink\MagicLink;
use Ramon\MagicLink\Token\IssuedToken;
use Ramon\MagicLink\Token\MagicToken;

/**
 * Monta o e-mail no idioma de quem recebe (o da conta ou, no cadastro, o da
 * página) e o põe na fila, como o AccountActivationMailer do core.
 */
class MagicLinkMailer
{
    public function __construct(
        protected SettingsRepositoryInterface $settings,
        protected Queue $queue,
        protected UrlGenerator $url,
        protected TranslatorInterface $translator,
        protected MagicLink $magicLink
    ) {
    }

    public function send(IssuedToken $issued): void
    {
        $model = $issued->model;
        $user = $model->user;

        $locale = $user?->getPreference('locale') ?: $this->translator->getLocale();
        $previousLocale = $this->translator->getLocale();
        $this->translator->setLocale($locale);

        $forum = (string) $this->settings->get('forum_title');
        $key = 'ramon-magic-link.email.'.$model->type;
        $minutes = $this->magicLink->lifetimeMinutes();
        $params = [
            'forum' => $forum,
            'code' => $issued->code,
            'minutes' => $minutes,
            'hours' => intdiv($minutes, 60),
        ];

        try {
            $subject = $this->translator->trans($key.'.subject', $params);
            $title = $this->translator->trans($key.'.title', $params);
            $intro = $this->translator->trans($key.'.intro', $params);
            $button = $this->translator->trans($key.'.button', $params);
            // "24 horas" lê melhor que "1440 minutos".
            $expiry = $this->translator->trans('ramon-magic-link.email.'.($minutes % 60 === 0 ? 'expiry_hours' : 'expiry_minutes'), $params);
            $ignore = $this->translator->trans('ramon-magic-link.email.ignore', $params);
        } finally {
            $this->translator->setLocale($previousLocale);
        }

        // Reautenticar é só com o código: o link não confirmaria quem está no navegador.
        $link = $model->type === MagicToken::TYPE_REAUTH
            ? null
            : $this->url->to('forum')->route('magic-link.confirm', ['token' => $issued->token]);

        $this->queue->push(new SendMagicLinkEmailJob(
            email: $model->email,
            // No cadastro a conta ainda não existe: a saudação usa o nome escolhido.
            displayName: $user !== null ? $user->display_name : (string) $model->dataValue('username'),
            subject: $subject,
            title: $title,
            intro: $intro,
            code: $issued->code,
            link: $link,
            button: $button,
            expiry: $expiry,
            ignore: $ignore,
            forumTitle: $forum,
            locale: $locale,
        ));
    }
}
