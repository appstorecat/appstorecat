<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('app_id')->constrained('apps')->cascadeOnDelete();
            $table->foreignId('version_id')->nullable()->constrained('app_versions')->nullOnDelete();
            $table->date('date');
            $table->decimal('rating', 3, 2)->default(0);
            $table->unsignedInteger('rating_count')->default(0);
            $table->json('rating_breakdown')->nullable();
            $table->integer('rating_delta')->nullable();
            $table->string('installs_range')->nullable();
            $table->unsignedBigInteger('file_size_bytes')->nullable();
            $table->timestamps();

            $table->unique(['app_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_metrics');
    }
};
