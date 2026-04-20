<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('app_id')->constrained('apps')->cascadeOnDelete();
            $table->string('version');
            $table->date('release_date')->nullable();
            $table->text('whats_new')->nullable();
            $table->unsignedBigInteger('file_size_bytes')->nullable();
            $table->timestamps();

            $table->unique(['app_id', 'version']);
            $table->index('release_date');
            $table->index(['app_id', 'release_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_versions');
    }
};
