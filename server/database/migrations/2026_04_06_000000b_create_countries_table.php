<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table) {
            $table->string('code', 2)->primary()
                ->comment('ISO-3166-1 alpha-2 country code in lowercase (e.g. "us", "tr"); natural PK.');
            $table->string('name', 100)
                ->comment('English country name displayed in UI (e.g. "United States").');
            $table->string('emoji', 10)
                ->comment('Flag emoji (e.g. "🇺🇸") used as a lightweight visual hint in the UI.');
            $table->boolean('is_active_ios')->default(false)
                ->comment('True if the App Store is scraped/synced for this country; false = skipped.');
            $table->boolean('is_active_android')->default(false)
                ->comment('True if Google Play is scraped/synced for this country; false = skipped.');
            $table->smallInteger('priority')->default(0)
                ->comment('Sort weight for country pickers; higher values appear first.');
            $table->json('ios_languages')->nullable()
                ->comment('Array of BCP-47 locale codes scraped per country on iOS; null = defaults.');
            $table->json('ios_cross_localizable')->nullable()
                ->comment('Locales reused across iOS storefronts to skip duplicate scrapes; null = none.');
            $table->json('android_languages')->nullable()
                ->comment('Array of BCP-47 locale codes scraped per country on Android; null = defaults.');
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
