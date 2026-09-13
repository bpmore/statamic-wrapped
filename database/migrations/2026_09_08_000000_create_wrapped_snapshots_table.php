<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wrapped_snapshots', function (Blueprint $table) {
            $table->bigIncrements('id');

            // One Wrapped per site per period. Multisite gets a row each.
            $table->string('site', 191);
            $table->string('period', 20);
            $table->string('period_key', 20);

            // Which source the numbers came from, and how far it can be
            // trusted. Both are shown in the UI: a Wrapped always says what it
            // was built from.
            $table->string('history_source', 50);
            $table->string('confidence', 20);

            $table->json('stats');

            // Set only when the site is younger than the period: the oldest
            // moment any trustworthy source knows about, so the screen can say
            // "since June" rather than "2026" — SPEC.md §1. Null otherwise.
            $table->timestamp('started_at')->nullable();

            $table->timestamp('generated_at');
            $table->string('generated_by', 191)->nullable();

            // Regenerating replaces the row rather than adding a second one.
            $table->unique(['site', 'period', 'period_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wrapped_snapshots');
    }
};
