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
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('app_id')->constrained('apps')->cascadeOnDelete();
            $table->foreignId('competitor_app_id')->constrained('apps')->cascadeOnDelete();
            $table->string('relationship')->default('direct');
            $table->timestamps();

            $table->unique(['user_id', 'app_id', 'competitor_app_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_competitors');
    }
};
