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
            $table->foreignId('app_id')
                ->comment('FK -> apps.id. Cascade on app delete. Drives the Update Timeline view.')
                ->constrained('apps')->cascadeOnDelete();
            $table->foreignId('version_id')->nullable()
                ->comment('FK -> app_versions.id at time of change; null when not tied to a specific version.')
                ->constrained('app_versions')->nullOnDelete();
            $table->string('locale', 10)
                ->comment('BCP-47 locale of the listing that changed (e.g. "en-US").');
            $table->string('field_changed')
                ->comment('Name of the listing field that changed (title, subtitle, description, screenshots, icon_url, price...).');
            $table->text('old_value')->nullable()
                ->comment('Previous value of the field (stringified JSON for arrays); null when no prior snapshot.');
            $table->text('new_value')->nullable()
                ->comment('New value of the field (stringified JSON for arrays); null when the field was cleared.');
            $table->timestamp('detected_at')
                ->comment('When the diff was detected by the pipeline; matches the newer snapshot fetched_at.');
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
