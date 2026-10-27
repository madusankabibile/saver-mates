<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The newest message id when a member joined a group. Read receipts only
 * count a member for messages sent after they joined (null = from the start).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversation_members', function (Blueprint $table) {
            $table->unsignedBigInteger('joined_after_message_id')->nullable()->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('conversation_members', fn (Blueprint $table) => $table->dropColumn('joined_after_message_id'));
    }
};
