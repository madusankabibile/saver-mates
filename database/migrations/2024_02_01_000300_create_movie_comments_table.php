<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movie_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('movie_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('movie_comments')->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index(['movie_id', 'parent_id']);
        });

        // Lets a notification point at the movie that was commented on.
        Schema::table('alerts', function (Blueprint $table) {
            $table->foreignId('movie_id')->nullable()->after('post_id')->constrained()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('alerts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('movie_id');
        });

        Schema::dropIfExists('movie_comments');
    }
};
