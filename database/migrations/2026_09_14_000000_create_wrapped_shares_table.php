<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wrapped_shares', function (Blueprint $table) {
            $table->bigIncrements('id');

            // The whole secret. 40 hex characters from a CSPRNG; nothing else
            // on the row is needed to find it, and nothing about the row can
            // be guessed from it.
            $table->string('token', 64)->unique();

            // Which Wrapped this was cut from, for listing under it in the
            // control panel. Not a foreign key: the snapshot may be
            // regenerated or removed, and the link keeps saying what it said.
            $table->string('site', 191);
            $table->string('period_key', 20);
            $table->index(['site', 'period_key']);

            // The frozen copy: the label and the cards exactly as published,
            // heading and body, already filtered for people.
            $table->string('label', 191);
            $table->json('cards');
            $table->boolean('people')->default(false);

            $table->string('created_by', 191)->nullable();
            $table->timestamp('created_at');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wrapped_shares');
    }
};
