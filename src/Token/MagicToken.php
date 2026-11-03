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
use Flarum\Database\AbstractModel;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $ref
 * @property string $token_hash
 * @property string $code_hash
 * @property string $type
 * @property string $email
 * @property int|null $user_id
 * @property array<string, mixed>|null $data
 * @property string|null $ip_address
 * @property int $used_count
 * @property int $otc_used_count
 * @property int $otc_attempts
 * @property Carbon|null $first_used_at
 * @property Carbon $created_at
 * @property-read User|null $user
 */
class MagicToken extends AbstractModel
{
    public const TYPE_SIGNIN = 'signin';
    public const TYPE_SIGNUP = 'signup';
    public const TYPE_REAUTH = 'reauth';

    protected $table = 'magic_link_tokens';

    public $timestamps = false;

    protected $casts = [
        'id' => 'integer',
        'user_id' => 'integer',
        'data' => 'array',
        'used_count' => 'integer',
        'otc_used_count' => 'integer',
        'otc_attempts' => 'integer',
        'first_used_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function dataValue(string $key): mixed
    {
        return $this->data[$key] ?? null;
    }
}
