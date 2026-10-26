<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('software', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('version', 40)->nullable();
            $table->string('os_type', 40)->nullable();
            $table->string('category', 40)->nullable();
            $table->string('file_path');
            $table->string('file_name');
            $table->unsignedBigInteger('file_size')->default(0);
            $table->string('file_mime', 80)->nullable();
            $table->string('thumbnail_path')->nullable();
            $table->unsignedBigInteger('downloads')->default(0);
            $table->unsignedBigInteger('views')->default(0);
            $table->timestamps();

            $table->index('created_at');
            $table->index('downloads');
            $table->index('category');
        });

        Schema::create('software_likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('software_id')->constrained('software')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['software_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('software_likes');
        Schema::dropIfExists('software');
    }
};
