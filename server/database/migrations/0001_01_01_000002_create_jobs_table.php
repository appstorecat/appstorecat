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
        Schema::create('jobs', function (Blueprint $table) {
            $table->id();
            $table->string('queue')->index()
                ->comment('Queue name (e.g. default, sync-tracked-ios); platform-separated per queue policy.');
            $table->longText('payload')
                ->comment('Serialized job class + data; consumed by queue workers.');
            $table->unsignedTinyInteger('attempts')
                ->comment('Number of times this job has been reserved/tried; incremented on each attempt.');
            $table->unsignedInteger('reserved_at')->nullable()
                ->comment('Unix timestamp when a worker picked up the job; null = still pending.');
            $table->unsignedInteger('available_at')
                ->comment('Unix timestamp when this job becomes eligible for processing (delay support).');
            $table->unsignedInteger('created_at')
                ->comment('Unix timestamp when the job was dispatched onto the queue.');
        });

        Schema::create('job_batches', function (Blueprint $table) {
            $table->string('id')->primary()
                ->comment('UUID for a queued job batch (bus::batch).');
            $table->string('name')
                ->comment('Human-readable batch name set at dispatch time.');
            $table->integer('total_jobs')
                ->comment('Total number of jobs initially added to the batch.');
            $table->integer('pending_jobs')
                ->comment('Jobs still to be processed; reaches 0 when the batch finishes.');
            $table->integer('failed_jobs')
                ->comment('Count of jobs in the batch that have failed.');
            $table->longText('failed_job_ids')
                ->comment('JSON array of job ids that failed; used for retry/diagnostics.');
            $table->mediumText('options')->nullable()
                ->comment('Serialized batch options (callbacks, allowFailures flag, etc.).');
            $table->integer('cancelled_at')->nullable()
                ->comment('Unix timestamp the batch was cancelled; null if still live.');
            $table->integer('created_at')
                ->comment('Unix timestamp when the batch was created.');
            $table->integer('finished_at')->nullable()
                ->comment('Unix timestamp when the last job in the batch finished; null while in progress.');
        });

        Schema::create('failed_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique()
                ->comment('UUID of the failed job instance; used by queue:retry.');
            $table->text('connection')
                ->comment('Queue connection name the job was dispatched on (e.g. redis, database).');
            $table->text('queue')
                ->comment('Queue name the failing job was pulled from.');
            $table->longText('payload')
                ->comment('Serialized original job payload; preserved for queue:retry.');
            $table->longText('exception')
                ->comment('Stack trace / exception string from the failure.');
            $table->timestamp('failed_at')->useCurrent()
                ->comment('When the failure was recorded; defaults to insert time.');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jobs');
        Schema::dropIfExists('job_batches');
        Schema::dropIfExists('failed_jobs');
    }
};
