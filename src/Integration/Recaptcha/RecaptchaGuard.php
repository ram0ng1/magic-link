<?php

/*
 * This file is part of ramon/magic-link.
 *
 * Copyright (c) 2026 Ramon Guilherme.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Ramon\MagicLink\Integration\Recaptcha;

use Flarum\Foundation\MaintenanceMode;
use Flarum\Foundation\ValidationException;
use Flarum\Locale\TranslatorInterface;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
use FoF\ReCaptcha\Utils;
use FoF\ReCaptcha\Validators\RecaptchaValidator;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Arr;
use Ramon\MagicLink\Api\RequestGuard;
use Ramon\MagicLink\Token\MagicToken;

/**
 * Com a fof/recaptcha, pedir o código pede o captcha, pelas mesmas opções dela:
 * "login" para entrar, "cadastro" para se cadastrar. Sem isso um robô mandaria
 * e-mails de código para qualquer endereço. Reautenticar é de quem já entrou,
 * e no modo de manutenção a fof também não pede.
 */
class RecaptchaGuard implements RequestGuard
{
    public function __construct(
        protected SettingsRepositoryInterface $settings,
        protected MaintenanceMode $maintenance,
        protected Container $container,
        protected TranslatorInterface $translator
    ) {
    }

    public function check(string $type, array $body, User $actor): void
    {
        if ($type === MagicToken::TYPE_REAUTH || ! Utils::isExtensionSetup($this->settings) || $this->maintenance->inMaintenanceMode()) {
            return;
        }

        if (! $this->settings->get($type === MagicToken::TYPE_SIGNUP ? 'fof-recaptcha.signup' : 'fof-recaptcha.signin')) {
            return;
        }

        $response = Arr::get($body, 'g-recaptcha-response');

        // A mesma mensagem que a fof mostra no login com senha.
        if (! is_string($response) || $response === '') {
            throw new ValidationException(['g-recaptcha-response' => $this->translator->trans('fof-recaptcha.lib.not_completed')]);
        }

        // Pelo container: é assim que a regra `recaptcha` da fof entra no validador.
        $this->container->make(RecaptchaValidator::class)->assertValid([
            'g-recaptcha-response' => $response,
            'g-recaptcha-action' => Arr::get($body, 'g-recaptcha-action'),
        ]);
    }
}
