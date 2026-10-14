<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Senders can edit and delete their messages. A deleted message keeps its row
 * (as "This message was deleted") so read positions and ordering stay intact;
 * its text and uploaded files are removed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->timestamp('edited_at')->nullable()->after('read_at');
            $table->timestamp('removed_at')->nullable()->after('edited_at');
        });
    }

    public function down(): void
    {
        Schema::table('messages', fn (Blueprint $table) => $table->dropColumn(['edited_at', 'removed_at']));
    }
};
