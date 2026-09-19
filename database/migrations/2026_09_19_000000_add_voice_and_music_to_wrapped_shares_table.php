<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wrapped_shares', function (Blueprint $table) {
            // Who the frozen cards talk to: `you` (the editor) or `we` (the
            // site, on a public page). Links made before this column existed
            // were written as `you`, so that is what they keep saying.
            $table->string('voice', 8)->default('you')->after('people');

            // A published song for the public page, by YouTube video id, with
            // the title and thumbnail YouTube gave us when the link was made
            // so the control panel can say which song without asking again.
            // The video export never uses these; see PROGRESS.md, Phase 8.
            $table->string('youtube_id', 16)->nullable()->after('voice');
            $table->string('youtube_title', 191)->nullable()->after('youtube_id');
            $table->string('youtube_thumbnail', 512)->nullable()->after('youtube_title');
        });
    }

    public function down(): void
    {
        Schema::table('wrapped_shares', function (Blueprint $table) {
            $table->dropColumn(['voice', 'youtube_id', 'youtube_title', 'youtube_thumbnail']);
        });
    }
};
