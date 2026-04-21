<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_statuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('app_id')->unique()
                ->comment('FK -> apps.id. One status row per app (hasOne); cascade on app delete.')
                ->constrained('apps')->cascadeOnDelete();

            $table->enum('status', ['queued', 'processing', 'completed', 'failed'])->default('queued')
                ->comment('Overall sync state: queued (awaiting worker), processing, completed, or failed.');
            $table->enum('current_step', ['identity', 'listings', 'metrics', 'finalize', 'reconciling'])->nullable()
                ->comment('Current pipeline step while status=processing; null when queued or done.');
            $table->unsignedSmallInteger('progress_done')->default(0)
                ->comment('Units completed for the current step (e.g. locales fetched); resets per step.');
            $table->unsignedSmallInteger('progress_total')->default(0)
                ->comment('Total units expected for the current step; paired with progress_done for UI %.');

            $table->json('failed_items')->nullable()
                ->comment('Array of {step, key, reason} for partial failures (e.g. a locale timed out); null when none.');
            $table->text('error_message')->nullable()
                ->comment('Terminal error message when status=failed; null otherwise.');

            $table->char('job_id', 36)->nullable()
                ->comment('UUID of the Laravel queue job currently handling this sync; null when idle.');

            $table->timestamp('started_at')->nullable()
                ->comment('When the current/last run entered the processing state.');
            $table->timestamp('completed_at')->nullable()
                ->comment('When the current/last run finished (success or failure); null while running.');
            $table->timestamp('next_retry_at')->nullable()
                ->comment('Earliest time a failed sync is eligible to retry; used by the retry scheduler.');

            $table->timestamps();

            $table->index(['status', 'next_retry_at']);
            $table->index('completed_at');
            $table->index('job_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_statuses');
    }
};
