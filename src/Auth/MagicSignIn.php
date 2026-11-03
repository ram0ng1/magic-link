<?php

/*
 * This file is part of ramon/magic-link.
 *
 * Copyright (c) 2026 Ramon Guilherme.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Ramon\MagicLink\Auth;

use Flarum\Http\RememberAccessToken;
use Flarum\Http\SessionAccessToken;
use Flarum\Http\SessionAuthenticator;
use Flarum\User\Event\LoggedIn;
use Flarum\User\RegistrationToken;
use Flarum\User\User;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Str;
use Psr\Http\Message\ServerRequestInterface;
use Ramon\MagicLink\MagicLink;
use Ramon\MagicLink\Token\InvalidMagicTokenException;
use Ramon\MagicLink\Token\MagicToken;

/**
 * O que acontece depois do link ou do código: entrar na conta que já existe
 * ou começar o cadastro, que termina no SignUpModal do core (assim os campos
 * de outras extensões, como os termos da fof/terms, continuam valendo).
 */
class MagicSignIn
{
    public function __construct(
        protected SessionAuthenticator $authenticator,
        protected Dispatcher $events,
        protected MagicLink $magicLink
    ) {
    }

    /**
     * @throws InvalidMagicTokenException
     */
    public function complete(MagicToken $token, ServerRequestInterface $request): MagicSignInResult
    {
        $user = $token->user_id !== null ? User::query()->find($token->user_id) : $this->findByEmail($token->email);

        // Pedido para uma conta cujo e-mail mudou depois: o link era do e-mail antigo.
        if ($user && strcasecmp($user->email, $token->email) !== 0) {
            throw new InvalidMagicTokenException();
        }

        if ($user) {
            return $this->logIn($user, $token, $request);
        }

        if ($token->type !== MagicToken::TYPE_SIGNUP) {
            throw new InvalidMagicTokenException();
        }

        $username = $token->dataValue('username');

        $registration = RegistrationToken::generate(
            MagicLink::PROVIDER,
            $token->ref,
            ['email' => $token->email],
            ['suggested' => array_filter(['username' => is_string($username) ? $username : null])]
        );
        $registration->save();

        return MagicSignInResult::register($registration, $token->email, is_string($username) ? $username : '');
    }

    protected function logIn(User $user, MagicToken $token, ServerRequestInterface $request): MagicSignInResult
    {
        // O código chegou no e-mail da conta: isso confirma o endereço.
        $user->activate();

        if ($this->magicLink->passwordless()) {
            $user->password = '';
        }

        $user->save();

        // A fof/gdpr pula a senha de quem tem um login externo.
        if (! $user->loginProviders()->where('provider', MagicLink::PROVIDER)->exists()) {
            $user->loginProviders()->create([
                'provider' => MagicLink::PROVIDER,
                'identifier' => (string) Str::uuid(),
            ]);
        }

        $accessToken = $token->dataValue('remember')
            ? RememberAccessToken::generate($user->id)
            : SessionAccessToken::generate($user->id);

        $accessToken->touch(request: $request);

        /** @var Session $session */
        $session = $request->getAttribute('session');
        $this->authenticator->logIn($session, $accessToken);

        $this->events->dispatch(new LoggedIn($user, $accessToken));

        return MagicSignInResult::loggedIn($user, $accessToken);
    }

    protected function findByEmail(string $email): ?User
    {
        return User::query()->where('email', $email)->first();
    }

    /**
     * O token de cadastro fica na sessão até o fórum abrir o SignUpModal, para
     * não ir na URL (histórico, Referer, logs).
     */
    public static function rememberRegistration(Session $session, MagicSignInResult $result): void
    {
        $session->put('magic-link.registration', [
            'token' => $result->registration?->token,
            'email' => $result->email,
            'username' => $result->username,
        ]);
    }
}
