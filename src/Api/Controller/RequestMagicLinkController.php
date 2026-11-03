<?php

/*
 * This file is part of ramon/magic-link.
 *
 * Copyright (c) 2026 Ramon Guilherme.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Ramon\MagicLink\Api\Controller;

use Flarum\Foundation\ValidationException;
use Flarum\Post\Exception\FloodingException;
use Flarum\Http\RequestUtil;
use Flarum\Locale\TranslatorInterface;
use Flarum\User\Exception\PermissionDeniedException;
use Flarum\User\User;
use Flarum\User\UserRepository;
use Flarum\User\UserValidator;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Ramon\MagicLink\Mail\MagicLinkMailer;
use Ramon\MagicLink\MagicLink;
use Ramon\MagicLink\Token\MagicToken;
use Ramon\MagicLink\Token\RateLimiter;
use Ramon\MagicLink\Token\TokenProvider;

/**
 * POST /api/magic-link: manda o código e o link para o e-mail.
 *
 * A resposta é sempre a mesma `ref`, exista a conta ou não (para um e-mail
 * desconhecido no login a ref é inventada e nada é enviado), e os limites
 * contam o pedido, não a conta: nada aqui revela quem tem conta no fórum.
 */
class RequestMagicLinkController implements RequestHandlerInterface
{
    public const MAX_PER_EMAIL = 5;
    public const EMAIL_WINDOW = 3600;

    public function __construct(
        protected TokenProvider $tokens,
        protected RateLimiter $limiter,
        protected MagicLinkMailer $mailer,
        protected MagicLink $magicLink,
        protected UserRepository $users,
        protected UserValidator $userValidator,
        protected TranslatorInterface $translator
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $body = (array) $request->getParsedBody();
        $actor = RequestUtil::getActor($request);
        $type = (string) Arr::get($body, 'type', MagicToken::TYPE_SIGNIN);

        if (! in_array($type, [MagicToken::TYPE_SIGNIN, MagicToken::TYPE_SIGNUP, MagicToken::TYPE_REAUTH], true)) {
            throw new ValidationException(['type' => $this->validationMessage('in', 'type')]);
        }

        // Campo invisível que só robô preenche: finge o envio.
        if (trim((string) Arr::get($body, 'honeypot', '')) !== '') {
            return $this->respond();
        }

        if ($type === MagicToken::TYPE_REAUTH) {
            $actor->assertRegistered();
            $this->assertWithinLimit($actor->email);

            return $this->issue($type, $actor->email, $actor, [], $request);
        }

        if (! $actor->isGuest()) {
            throw new PermissionDeniedException();
        }

        $identification = trim((string) Arr::get($body, 'identification', Arr::get($body, 'email', '')));
        $remember = (bool) Arr::get($body, 'remember', false);

        if ($identification === '') {
            // O campo se chama "email" no cadastro e "identification" no login.
            $field = $type === MagicToken::TYPE_SIGNUP ? 'email' : 'identification';

            throw new ValidationException([$field => $this->validationMessage('required', 'email')]);
        }

        if ($type === MagicToken::TYPE_SIGNUP) {
            return $this->signUp($identification, $body, $remember, $request);
        }

        $this->assertWithinLimit($identification);

        $user = $this->findUser($identification);

        if (! $user) {
            return $this->respond();
        }

        return $this->issue(MagicToken::TYPE_SIGNIN, $user->email, $user, ['remember' => $remember], $request);
    }

    /**
     * @param array<string, mixed> $body
     */
    protected function signUp(string $email, array $body, bool $remember, ServerRequestInterface $request): ResponseInterface
    {
        if (! $this->magicLink->allowsSignUp()) {
            throw new PermissionDeniedException();
        }

        $email = Str::lower($email);

        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
            throw new ValidationException(['email' => $this->validationMessage('email', 'email')]);
        }

        // O nome de usuário é público e é validado antes de olhar o e-mail:
        // assim o erro é o mesmo com ou sem conta. Vazio não passa, porque
        // com o token o core não exige o nome no /register.
        $username = trim((string) Arr::get($body, 'username', ''));

        if ($username === '') {
            throw new ValidationException(['username' => $this->validationMessage('required', 'username')]);
        }

        $this->userValidator->assertValid(['username' => $username]);
        $this->assertWithinLimit($email);

        // Quem já tem conta recebe o código de entrar: o cadastro não conta
        // que o e-mail é usado, e a pessoa entra do mesmo jeito.
        if ($user = $this->findUser($email)) {
            return $this->issue(MagicToken::TYPE_SIGNIN, $user->email, $user, ['remember' => $remember], $request);
        }

        return $this->issue(MagicToken::TYPE_SIGNUP, $email, null, ['remember' => true, 'username' => $username], $request);
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function issue(string $type, string $email, ?User $user, array $data, ServerRequestInterface $request): ResponseInterface
    {
        $ip = $request->getAttribute('ipAddress');
        $issued = $this->tokens->issue($type, $email, $user, $data, is_string($ip) ? $ip : null);

        $this->mailer->send($issued);

        return $this->respond($issued->model->ref);
    }

    /**
     * Conta o pedido por e-mail ou nome digitado, exista a conta ou não: o
     * 429 vale igual para os dois, então pode ser mostrado à pessoa.
     *
     * @throws FloodingException
     */
    protected function assertWithinLimit(string $identification): void
    {
        if ($this->limiter->hit('email:'.Str::lower($identification), self::EMAIL_WINDOW) > self::MAX_PER_EMAIL) {
            throw new FloodingException();
        }
    }

    protected function findUser(string $identification): ?User
    {
        $user = $this->users->findByIdentification($identification);

        // E-mails ficam como foram digitados; no PostgreSQL e no SQLite a
        // comparação diferencia maiúsculas.
        if (! $user && str_contains($identification, '@')) {
            $user = User::query()->whereRaw('LOWER(email) = ?', [Str::lower($identification)])->first();
        }

        return $user;
    }

    /**
     * As mensagens de validação do Flarum usam o `:attribute` do Laravel, que o
     * tradutor (ICU) não troca: quem troca é o validador, que aqui não roda.
     */
    protected function validationMessage(string $rule, string $attribute): string
    {
        $name = $this->translator->trans('validation.attributes.'.$attribute);

        return str_replace(':attribute', $name !== 'validation.attributes.'.$attribute ? $name : $attribute, $this->translator->trans('validation.'.$rule));
    }

    protected function respond(?string $ref = null): ResponseInterface
    {
        return new JsonResponse([
            'ref' => $ref ?? (string) Str::uuid(),
            'codeLength' => TokenProvider::CODE_LENGTH,
        ], 201);
    }
}
