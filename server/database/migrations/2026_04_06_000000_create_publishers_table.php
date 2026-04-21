<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('publishers', function (Blueprint $table) {
            $table->id();
            $table->string('name')
                ->comment('Developer / publisher name as shown on the store listing.');
            $table->string('external_id')->nullable()
                ->comment('Store-provided publisher id (iTunes artistId / Play developerId); null if unknown.');
            $table->unsignedTinyInteger('platform')
                ->comment('Store platform: 1=iOS, 2=Android. See App\\Enums\\Platform. Unique with external_id.');
            $table->text('url')->nullable()
                ->comment('Publisher website / store page URL scraped from the listing; null when absent.');
            $table->timestamps();

            $table->unique(['platform', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('publishers');
    }
};
