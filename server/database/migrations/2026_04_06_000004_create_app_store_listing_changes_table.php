<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_store_listing_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('app_id')->constrained('apps')->cascadeOnDelete();
            $table->foreignId('version_id')->nullable()->constrained('app_versions')->nullOnDelete();
            $table->string('locale', 10);
            $table->string('field_changed');
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->timestamp('detected_at');
            $table->timestamps();

            $table->index(['app_id', 'detected_at']);
            $table->index(['app_id', 'field_changed', 'detected_at']);
            $table->index('version_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_store_listing_changes');
    }
};
