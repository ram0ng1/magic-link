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

use Flarum\Api\Schema;
use Ramon\MagicLink\MagicLink;
use Ramon\MagicLink\Token\TokenProvider;

class ForumResourceFields
{
    public function __construct(
        protected MagicLink $magicLink
    ) {
    }

    /** @return array<Schema\Attribute> */
    public function __invoke(): array
    {
        return [
            // O fórum e o admin escondem os campos de senha que só falhariam.
            Schema\Boolean::make('magicLinkPasswordless')
                ->get(fn () => $this->magicLink->passwordless()),
            Schema\Integer::make('magicLinkCodeLength')
                ->get(fn () => TokenProvider::CODE_LENGTH),
        ];
    }
}
