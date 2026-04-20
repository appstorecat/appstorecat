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
            $table->foreignId('app_id')->unique()->constrained('apps')->cascadeOnDelete();

            $table->enum('status', ['queued', 'processing', 'completed', 'failed'])->default('queued');
            $table->enum('current_step', ['identity', 'listings', 'metrics', 'finalize', 'reconciling'])->nullable();
            $table->unsignedSmallInteger('progress_done')->default(0);
            $table->unsignedSmallInteger('progress_total')->default(0);

            $table->json('failed_items')->nullable();
            $table->text('error_message')->nullable();

            $table->char('job_id', 36)->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('next_retry_at')->nullable();

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
