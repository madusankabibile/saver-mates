<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->string('title', 180)->nullable()->after('user_id');
        });

        // The column was an enum of standard/colored. Widening it to a string
        // keeps "article" - and anything added later - from needing DBAL.
        DB::statement("ALTER TABLE posts MODIFY type VARCHAR(20) NOT NULL DEFAULT 'standard'");
    }

    public function down(): void
    {
        DB::statement("UPDATE posts SET type = 'standard' WHERE type NOT IN ('standard','colored')");
        DB::statement("ALTER TABLE posts MODIFY type ENUM('standard','colored') NOT NULL DEFAULT 'standard'");

        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn('title');
        });
    }
};
