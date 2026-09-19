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
        });
    }

    public function down(): void
    {
        Schema::table('wrapped_shares', function (Blueprint $table) {
            $table->dropColumn('voice');
        });
    }
};
