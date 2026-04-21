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
        Schema::create('cache', function (Blueprint $table) {
            $table->string('key')->primary()
                ->comment('Cache key; prefix usually includes app namespace.');
            $table->mediumText('value')
                ->comment('Serialized cached payload (PHP serialize/igbinary per config).');
            $table->integer('expiration')->index()
                ->comment('Unix timestamp when the entry expires; indexed for GC sweeps.');
        });

        Schema::create('cache_locks', function (Blueprint $table) {
            $table->string('key')->primary()
                ->comment('Lock key (usually cache-key + suffix); identifies the mutex.');
            $table->string('owner')
                ->comment('Random token of the holder; required to release the lock safely.');
            $table->integer('expiration')->index()
                ->comment('Unix timestamp when the lock auto-releases to avoid deadlocks.');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cache');
        Schema::dropIfExists('cache_locks');
    }
};
