<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_competitors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->comment('FK -> users.id. Owner of this competitor mapping (per-user, not global). Cascade on user delete.')
                ->constrained()->cascadeOnDelete();
            $table->foreignId('app_id')
                ->comment('FK -> apps.id. The subject app. Cascade on app delete.')
                ->constrained('apps')->cascadeOnDelete();
            $table->foreignId('competitor_app_id')
                ->comment('FK -> apps.id. The app being compared against; cascade on app delete.')
                ->constrained('apps')->cascadeOnDelete();
            $table->string('relationship')->default('direct')
                ->comment('Relationship kind: direct, indirect, aspiration. See App\\Enums\\CompetitorRelationship.');
            $table->timestamps();

            $table->unique(['user_id', 'app_id', 'competitor_app_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_competitors');
    }
};
