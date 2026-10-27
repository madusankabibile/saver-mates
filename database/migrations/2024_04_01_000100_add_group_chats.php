<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Group chats live in the same conversations table as private chats, so
 * sending, attachments and the drive picker work unchanged. A group has no
 * user_one/user_two; its people are rows in conversation_members, which also
 * hold each member's read position and mute setting.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Private chats keep both participant columns; groups leave them null.
        // (MySQL allows any number of NULL pairs under the unique index.)
        DB::statement('ALTER TABLE conversations MODIFY user_one_id BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE conversations MODIFY user_two_id BIGINT UNSIGNED NULL');

        Schema::table('conversations', function (Blueprint $table) {
            $table->boolean('is_group')->default(false)->after('id')->index();
            $table->string('name', 80)->nullable()->after('is_group');
            $table->foreignId('created_by')->nullable()->after('name')->constrained('users')->nullOnDelete();
        });

        Schema::create('conversation_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 10)->default('member'); // admin | member
            $table->unsignedBigInteger('last_read_message_id')->nullable();
            $table->dateTime('muted_until')->nullable(); // far future = muted until turned back on
            $table->timestamps();

            $table->unique(['conversation_id', 'user_id']);
            $table->index('user_id');
        });

        // "Nimal added Sanduni" lines in a group are messages with an event.
        Schema::table('messages', function (Blueprint $table) {
            $table->string('event', 20)->nullable()->after('body');
        });
    }

    public function down(): void
    {
        Schema::table('messages', fn (Blueprint $table) => $table->dropColumn('event'));
        Schema::dropIfExists('conversation_members');

        DB::table('conversations')->where('is_group', true)->delete();

        Schema::table('conversations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn(['is_group', 'name']);
        });

        DB::statement('ALTER TABLE conversations MODIFY user_one_id BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE conversations MODIFY user_two_id BIGINT UNSIGNED NOT NULL');
    }
};
