<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Flat per-user folders for organizing tracked apps. Apps reference a
     * folder via user_apps.folder_id; deleting a folder leaves the app
     * tracked but unsorted (folder_id → NULL).
     *
     * Color is stored as a slug (slate, red, orange, …) and resolved to
     * Tailwind classes on the client. No nested folders in this iteration.
     */
    public function up(): void
    {
        Schema::create('folders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->comment('FK -> users.id. Owner of the folder; cascade on user delete.')
                ->constrained()->cascadeOnDelete();
            $table->string('name', 60)
                ->comment('User-visible folder name. Unique per user (case-sensitive).');
            $table->string('color', 20)
                ->comment('Color slug: slate|red|orange|amber|yellow|green|teal|blue|purple|pink.');
            $table->smallInteger('sort_order')->default(0)
                ->comment('Manual ordering weight for sidebar; higher = shown first.');
            $table->timestamps();

            $table->unique(['user_id', 'name']);
            $table->index(['user_id', 'sort_order']);
        });

        Schema::table('user_apps', function (Blueprint $table) {
            $table->foreignId('folder_id')->nullable()
                ->after('app_id')
                ->comment('FK -> folders.id; NULL when the tracked app is unsorted. Null on folder delete.')
                ->constrained('folders')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('user_apps', function (Blueprint $table) {
            $table->dropConstrainedForeignId('folder_id');
        });

        Schema::dropIfExists('folders');
    }
};
