<?php

/*
 * This file is part of ramon/magic-link.
 *
 * Copyright (c) 2026 Ramon Guilherme.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Ramon\MagicLink;

use Flarum\Api\Context;
use Flarum\Api\Endpoint;
use Flarum\Api\Resource\ForumResource;
use Flarum\Api\Resource\UserResource;
use Flarum\Extend;
use Flarum\User\Event\RegisteringFromProvider;
use Flarum\User\User;

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js')
        ->css(__DIR__.'/less/forum.less')
        ->content(Forum\RegistrationPayload::class),

    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js'),

    new Extend\Locales(__DIR__.'/locale'),

    (new Extend\View())
        ->namespace('ramon-magic-link', __DIR__.'/views'),

    (new Extend\ServiceProvider())
        ->register(MagicLinkServiceProvider::class),

    (new Extend\Settings())
        ->default('ramon-magic-link.allow_password', false)
        ->default('ramon-magic-link.token_lifetime', MagicLink::MAX_LIFETIME),

    (new Extend\ApiResource(ForumResource::class))
        ->fields(Api\ForumResourceFields::class),

    (new Extend\ApiResource(UserResource::class))
        ->endpoint(Endpoint\Create::class, function (Endpoint\Create $endpoint) {
            // A forma class-string do hook é instanciada sem o container.
            return $endpoint->before(fn (Context $context) => resolve(Api\RequireRegistrationToken::class)($context));
        }),

    (new Extend\Auth())
        ->addPasswordChecker('ramon-magic-link', Auth\MagicLinkPasswordChecker::class),

    (new Extend\Middleware('forum'))
        ->add(Middleware\RestrictPasswordRoutes::class),

    (new Extend\Middleware('api'))
        ->add(Middleware\RestrictPasswordRoutes::class),

    (new Extend\Event())
        ->listen('eloquent.saving: '.User::class, Listener\WipePassword::class)
        ->listen(RegisteringFromProvider::class, Listener\ClearProviderPassword::class),

    (new Extend\ThrottleApi())
        ->set('magic-link.request', Api\Throttler\MagicLinkThrottler::class),

    (new Extend\Console())
        ->command(Console\PruneTokensCommand::class)
        ->command(Console\LoginUrlCommand::class)
        ->command(Console\PurgePasswordsCommand::class)
        ->schedule(Console\PruneTokensCommand::class, Console\PruneTokensSchedule::class),

    (new Extend\Routes('api'))
        ->post('/magic-link', 'magic-link.request', Api\Controller\RequestMagicLinkController::class),

    (new Extend\Routes('forum'))
        ->post('/magic-link/verify', 'magic-link.verify', Forum\Controller\VerifyCodeController::class)
        ->get('/magic-link/{token:[A-Za-z0-9]+}', 'magic-link.confirm', Forum\Controller\MagicLinkViewController::class)
        ->post('/magic-link/{token:[A-Za-z0-9]+}', 'magic-link.confirm.submit', Forum\Controller\MagicLinkController::class),

    // Integrações opcionais: só valem com a outra extensão ativa.
    (new Extend\Conditional())
        ->whenExtensionEnabled('fof-recaptcha', fn () => [
            (new Extend\ServiceProvider())->register(Integration\Recaptcha\RecaptchaServiceProvider::class),
        ]),

];
