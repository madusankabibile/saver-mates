<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('category', 40)->nullable();
            $table->string('video_path');
            $table->string('video_mime', 80)->nullable();
            $table->unsignedBigInteger('video_size')->default(0);
            $table->string('thumbnail_path')->nullable();
            $table->unsignedInteger('duration')->nullable(); // seconds
            $table->unsignedBigInteger('views')->default(0);
            $table->timestamps();

            $table->index('created_at');
            $table->index('views');
            $table->index('category');
        });

        Schema::create('movie_subtitles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('movie_id')->constrained()->cascadeOnDelete();
            $table->string('label', 60);
            $table->string('lang', 12)->default('en');
            $table->string('path');
            $table->timestamps();
        });

        Schema::create('movie_likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('movie_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['movie_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movie_likes');
        Schema::dropIfExists('movie_subtitles');
        Schema::dropIfExists('movies');
    }
};
