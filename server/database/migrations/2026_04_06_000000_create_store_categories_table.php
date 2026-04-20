<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_categories', function (Blueprint $table) {
            $table->id();
            $table->string('external_id')->nullable();
            $table->string('name');
            $table->string('slug');
            $table->unsignedTinyInteger('platform');
            $table->string('type')->default('app');
            $table->foreignId('parent_id')->nullable()->constrained('store_categories')->nullOnDelete();
            $table->smallInteger('priority')->default(0);
            $table->timestamps();

            $table->unique(['platform', 'slug', 'type']);
            $table->index(['platform', 'type']);
            $table->index(['platform', 'external_id']);
            $table->index(['platform', 'parent_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_categories');
    }
};
