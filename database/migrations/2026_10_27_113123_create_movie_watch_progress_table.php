<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('movie_watch_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('movie_id')->constrained()->cascadeOnDelete();
            $table->integer('current_time')->default(0); // Current watch position in seconds
            $table->integer('duration')->default(0); // Total duration of the movie
            $table->decimal('progress_percent', 5, 2)->default(0); // Percentage watched
            $table->timestamp('last_watched_at')->nullable(); // Last time user watched
            $table->timestamps();

            $table->unique(['user_id', 'movie_id']);
            $table->index('last_watched_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('movie_watch_progress');
    }
};
