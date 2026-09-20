<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wrapped_shares', function (Blueprint $table) {
            // A soundtrack for the public page, by handle: one of the bundled
            // tracks, a config track, or an upload. The other kind of music,
            // a YouTube video, is the youtube_* columns; a link has one or
            // the other or neither.
            $table->string('soundtrack', 64)->nullable()->after('youtube_thumbnail');
        });
    }

    public function down(): void
    {
        Schema::table('wrapped_shares', function (Blueprint $table) {
            $table->dropColumn('soundtrack');
        });
    }
};
