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
            $table->foreignId('app_id')
                ->comment('FK -> apps.id. Owner app of this version row. Cascade on app delete.')
                ->constrained('apps')->cascadeOnDelete();
            $table->string('version')
                ->comment('Store-reported version string (e.g. "3.14.0"). Unique per app.');
            $table->date('release_date')->nullable()
                ->comment('Release date of this version as reported by the store; null if unknown.');
            $table->text('whats_new')->nullable()
                ->comment('Release notes in the default locale; per-locale variants live in app_store_listings.');
            $table->unsignedBigInteger('file_size_bytes')->nullable()
                ->comment('Binary size in bytes from the store listing; null when not reported.');
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
