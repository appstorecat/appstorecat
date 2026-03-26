<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table) {
            $table->string('code', 2)->primary();
            $table->string('name', 100);
            $table->string('emoji', 10);
            $table->boolean('is_active_ios')->default(false);
            $table->boolean('is_active_android')->default(false);
            $table->smallInteger('priority')->default(0);
            $table->json('ios_languages')->nullable();
            $table->json('ios_cross_localizable')->nullable();
            $table->json('android_languages')->nullable();
            $table->timestamps();

            $table->index('is_active_ios');
            $table->index('is_active_android');
            $table->index('priority');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('countries');
    }
};
