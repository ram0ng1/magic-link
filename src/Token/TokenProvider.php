<?php

/*
 * This file is part of ramon/magic-link.
 *
 * Copyright (c) 2026 Ramon Guilherme.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Ramon\MagicLink\Token;

use Carbon\Carbon;
use Flarum\User\User;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use Ramon\MagicLink\MagicLink;

/**
 * Emite e confere os tokens, com estas regras de uso:
 *
 * - só o pedido mais recente de cada e-mail vale: pedir outro código
 *   invalida o anterior;
 * - o link vale por um tempo total (padrão 24 h) e pode ser aberto algumas
 *   vezes nos 10 minutos depois do primeiro uso, porque leitores de e-mail
 *   e antivírus abrem o link antes da pessoa;
 * - o código é de uso único; usar o código invalida o link e vice-versa;
 * - o código tem 5 tentativas por pedido e 15 por e-mail em 24 h, somados
 *   todos os pedidos: não dá para adivinhar pedindo códigos sem parar.
 */
class TokenProvider
{
    public const MAX_LINK_USES = 7;
    public const LINK_VALIDITY_AFTER_USE = 600;
    public const MAX_CODE_ATTEMPTS = 5;
    public const MAX_FAILURES_PER_EMAIL = 15;
    public const FAILURE_WINDOW = 86400;
    public const CODE_LENGTH = 6;
    // Trocar o e-mail pode falhar na validação depois de conferir o código;
    // o mesmo código vale de novo por alguns minutos, só para a mesma conta.
    public const MAX_REAUTH_USES = 3;

    public function __construct(
        protected MagicLink $magicLink,
        protected ConnectionInterface $db,
        protected RateLimiter $limiter
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function issue(string $type, string $email, ?User $user, array $data = [], ?string $ip = null): IssuedToken
    {
        $token = Str::random(40);
        $code = self::generateCode();

        // Só o último pedido vale: quem tenta adivinhar não junta várias refs vivas.
        MagicToken::query()
            ->where('email', $email)
            ->where('type', $type)
            ->where('created_at', '>=', Carbon::now()->subMinutes($this->magicLink->lifetimeMinutes()))
            ->update(['used_count' => self::MAX_LINK_USES, 'otc_attempts' => self::MAX_CODE_ATTEMPTS]);

        $model = new MagicToken();
        $model->ref = (string) Str::uuid();
        $model->token_hash = self::hashToken($token);
        $model->code_hash = self::hashCode($model->ref, $code);
        $model->type = $type;
        $model->email = $email;
        $model->user_id = $user?->id;
        $model->data = $data ?: null;
        $model->ip_address = $ip;
        $model->created_at = Carbon::now();
        $model->save();

        return new IssuedToken($model, $token, $code);
    }

    /**
     * Confere o token sem gastar um uso: a página de confirmação do link só
     * mostra o botão quando ele ainda vale.
     */
    public function peekLink(#[\SensitiveParameter] string $token): ?MagicToken
    {
        $model = MagicToken::query()->where('token_hash', self::hashToken($token))->first();

        return $model && $this->linkUsable($model) ? $model : null;
    }

    /**
     * @throws InvalidMagicTokenException
     */
    public function consumeLink(#[\SensitiveParameter] string $token): MagicToken
    {
        $hash = self::hashToken($token);

        return $this->db->transaction(function () use ($hash) {
            // Trava a linha: dois cliques simultâneos não passam do limite de usos.
            $model = MagicToken::query()->where('token_hash', $hash)->lockForUpdate()->first();

            if (! $model || ! $this->linkUsable($model)) {
                throw new InvalidMagicTokenException();
            }

            $model->used_count++;
            $model->first_used_at ??= Carbon::now();
            // O link entrou: o código do mesmo e-mail não serve mais para ninguém.
            $model->otc_attempts = self::MAX_CODE_ATTEMPTS;
            $model->save();

            return $model;
        });
    }

    /**
     * Inutiliza o token (link e código), quando ele não pode mais dar certo.
     */
    public function revoke(MagicToken $model): void
    {
        MagicToken::query()->whereKey($model->id)->update([
            'used_count' => self::MAX_LINK_USES,
            'otc_attempts' => self::MAX_CODE_ATTEMPTS,
        ]);
    }

    /**
     * @param list<string> $types tipos aceitos nesta conferência
     *
     * @throws InvalidMagicTokenException
     */
    public function consumeCode(string $ref, #[\SensitiveParameter] string $code, array $types, ?User $user = null): MagicToken
    {
        $code = preg_replace('/\D/', '', $code) ?? '';

        // A tentativa errada precisa ficar gravada, então a transação não
        // pode desfazer: o resultado sai dela e a exceção vem depois.
        [$model, $failedEmail] = $this->db->transaction(function () use ($ref, $code, $types, $user) {
            $model = MagicToken::query()->where('ref', $ref)->lockForUpdate()->first();

            if (! $model
                || ! in_array($model->type, $types, true)
                || ($user !== null && $model->user_id !== $user->id)
                || ! $this->codeUsable($model)) {
                return [null, null];
            }

            if (strlen($code) !== self::CODE_LENGTH || ! hash_equals($model->code_hash, self::hashCode($model->ref, $code))) {
                $model->otc_attempts++;
                $model->save();

                return [null, $model->email];
            }

            $model->otc_used_count++;
            $model->first_used_at ??= Carbon::now();
            $model->save();

            return [$model, null];
        });

        if ($failedEmail !== null) {
            $this->limiter->hit(self::failureKey($failedEmail), self::FAILURE_WINDOW);
        }

        if (! $model instanceof MagicToken) {
            throw new InvalidMagicTokenException();
        }

        return $model;
    }

    public function prune(): int
    {
        return MagicToken::query()
            ->where('created_at', '<', Carbon::now()->subMinutes($this->magicLink->lifetimeMinutes()))
            ->delete();
    }

    protected function linkUsable(MagicToken $model): bool
    {
        // O link de reautenticação não existe: só o código confirma a pessoa.
        if ($model->type === MagicToken::TYPE_REAUTH) {
            return false;
        }

        if ($model->otc_used_count > 0 || $model->used_count >= self::MAX_LINK_USES || $this->expired($model)) {
            return false;
        }

        return ! $model->first_used_at
            || $model->first_used_at->diffInSeconds(Carbon::now(), true) <= self::LINK_VALIDITY_AFTER_USE;
    }

    protected function codeUsable(MagicToken $model): bool
    {
        if ($model->otc_attempts >= self::MAX_CODE_ATTEMPTS
            || $this->expired($model)
            || $this->limiter->count(self::failureKey($model->email)) >= self::MAX_FAILURES_PER_EMAIL) {
            return false;
        }

        if ($model->type !== MagicToken::TYPE_REAUTH) {
            return $model->otc_used_count === 0;
        }

        return $model->otc_used_count < self::MAX_REAUTH_USES
            && (! $model->first_used_at || $model->first_used_at->diffInSeconds(Carbon::now(), true) <= self::LINK_VALIDITY_AFTER_USE);
    }

    protected static function failureKey(string $email): string
    {
        return 'failures:'.Str::lower($email);
    }

    protected function expired(MagicToken $model): bool
    {
        return $model->created_at->lessThan(Carbon::now()->subMinutes($this->magicLink->lifetimeMinutes()));
    }

    public static function generateCode(): string
    {
        return str_pad((string) random_int(0, 10 ** self::CODE_LENGTH - 1), self::CODE_LENGTH, '0', STR_PAD_LEFT);
    }

    public static function hashToken(#[\SensitiveParameter] string $token): string
    {
        return hash('sha256', $token);
    }

    public static function hashCode(string $ref, #[\SensitiveParameter] string $code): string
    {
        return hash('sha256', $ref.'|'.$code);
    }
}
