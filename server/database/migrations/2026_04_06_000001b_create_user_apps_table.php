<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_apps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->comment('FK -> users.id. The user who is tracking the app. Cascade on user delete.')
                ->constrained()->cascadeOnDelete();
            $table->foreignId('app_id')
                ->comment('FK -> apps.id. The tracked app. Cascade on app delete.')
                ->constrained('apps')->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent()
                ->comment('When the user started tracking this app; no updated_at since row is immutable.');

            $table->unique(['user_id', 'app_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_apps');
    }
};
