<?php

/*
 * This file is part of ramon/magic-link.
 *
 * Copyright (c) 2026 Ramon Guilherme.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

return Migration::createTable('magic_link_tokens', function (Blueprint $table) {
    $table->increments('id');
    // Referência pública devolvida ao navegador para conferir o código.
    $table->string('ref', 36)->unique();
    // Só o hash do token do link: o banco vazado não abre sessões.
    $table->string('token_hash', 64)->unique();
    $table->string('code_hash', 64);
    $table->string('type', 16);
    $table->string('email', 150);
    $table->unsignedInteger('user_id')->nullable();
    $table->text('data')->nullable();
    $table->string('ip_address', 45)->nullable();
    $table->unsignedSmallInteger('used_count')->default(0);
    $table->unsignedSmallInteger('otc_used_count')->default(0);
    $table->unsignedSmallInteger('otc_attempts')->default(0);
    $table->dateTime('first_used_at')->nullable();
    $table->dateTime('created_at');

    $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
    // Limites por e-mail e por IP contam os pedidos recentes.
    $table->index(['email', 'created_at']);
    $table->index(['ip_address', 'created_at']);
    $table->index('created_at');
});
