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

use Flarum\Foundation\DispatchEventsTrait;
use Flarum\Http\AccessToken;
use Flarum\Http\RememberAccessToken;
use Flarum\Http\SessionAccessToken;
use Flarum\Http\SessionAuthenticator;
use Flarum\User\Event\LoggedIn;
use Flarum\User\RegistrationToken;
use Flarum\User\User;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Session\Session;
use Psr\Http\Message\ServerRequestInterface;
use Ramon\MagicLink\MagicLink;
use Ramon\MagicLink\Token\InvalidMagicTokenException;
use Ramon\MagicLink\Token\MagicToken;
use Ramon\MagicLink\Token\TokenProvider;

/**
 * O que acontece depois do link ou do código: entrar na conta que já existe
 * ou começar o cadastro, que termina no SignUpModal do core (assim os campos
 * de outras extensões, como os termos da fof/terms, continuam valendo).
 */
class MagicSignIn
{
    use DispatchEventsTrait;

    public function __construct(
        protected SessionAuthenticator $authenticator,
        protected Dispatcher $events,
        protected MagicLink $magicLink,
        protected TokenProvider $tokens
    ) {
    }

    /**
     * @throws InvalidMagicTokenException
     */
    public function complete(MagicToken $token, ServerRequestInterface $request): MagicSignInResult
    {
        $user = $token->user_id !== null ? User::query()->find($token->user_id) : $this->findByEmail($token->email);

        // Pedido para uma conta cujo e-mail mudou depois (o link era do e-mail
        // antigo), ou cadastro depois que o admin fechou os cadastros: o token
        // não vai dar certo nunca mais, então some de vez.
        if (($user && strcasecmp($user->email, $token->email) !== 0)
            || (! $user && ($token->type !== MagicToken::TYPE_SIGNUP || ! $this->magicLink->allowsSignUp()))) {
            $this->tokens->revoke($token);

            throw new InvalidMagicTokenException();
        }

        if ($user) {
            return $this->logIn($user, $token, $request);
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
        // Conta que nunca confirmou o e-mail pode ter sido criada por outra
        // pessoa com o endereço de quem agora prova ser o dono. Antes de
        // confirmar, sai tudo o que dava acesso a ela: senha, sessões e logins externos.
        if (! $user->is_email_confirmed) {
            $user->password = '';
            AccessToken::query()->where('user_id', $user->id)->delete();
            $user->loginProviders()->delete();
        }

        // O código chegou no e-mail da conta: isso confirma o endereço.
        $user->activate();

        if ($this->magicLink->passwordless()) {
            $user->password = '';
        }

        $user->save();
        $this->dispatchEventsFor($user);

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
