<?php

/*
 * This file is part of ramon/magic-link.
 *
 * Copyright (c) 2026 Ramon Guilherme.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Ramon\MagicLink\Api;

use Flarum\Api\Context;
use Flarum\Api\Schema;

class GdprForumFields
{
    /** @return array<Schema\Attribute> */
    public function __invoke(): array
    {
        return [
            // Mesmo atributo da fof/oauth, com a mesma regra do servidor da gdpr.
            Schema\Boolean::make('passwordlessSignUp')
                ->get(fn ($model, Context $context) => ! $context->getActor()->isGuest() && $context->getActor()->loginProviders()->count() > 0),
        ];
    }
}
