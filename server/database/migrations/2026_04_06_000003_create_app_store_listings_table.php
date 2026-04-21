<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_store_listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('app_id')
                ->comment('FK -> apps.id. Cascade on app delete. Unique with (version_id, locale).')
                ->constrained('apps')->cascadeOnDelete();
            $table->foreignId('version_id')->nullable()
                ->comment('FK -> app_versions.id; null when listing predates version tracking. Null on version delete.')
                ->constrained('app_versions')->nullOnDelete();
            $table->string('locale', 10)
                ->comment('BCP-47 locale of this listing snapshot (e.g. "en-US", "tr").');
            $table->string('title')
                ->comment('Localized app title shown on the store listing.');
            $table->string('subtitle')->nullable()
                ->comment('iOS short tagline under the title; null on Android or when not set.');
            $table->text('description')
                ->comment('Full localized store description as shown on the listing.');
            $table->text('promotional_text')->nullable()
                ->comment('iOS promotional text block, editable without a new version; null when unused.');
            $table->text('whats_new')->nullable()
                ->comment('Per-locale release notes for this version; overrides app_versions.whats_new for display.');
            $table->json('screenshots')->nullable()
                ->comment('Array of screenshot URLs ordered as shown on the store; may include device-specific buckets.');
            $table->text('icon_url')->nullable()
                ->comment('Icon URL captured with this listing snapshot; per-locale since stores allow localized icons.');
            $table->text('video_url')->nullable()
                ->comment('App preview / promo video URL from the listing; null when none.');
            $table->decimal('price', 10, 2)->default(0)
                ->comment('Listed price at fetch time in the storefront currency; 0.00 for free apps.');
            $table->string('currency', 3)->nullable()
                ->comment('ISO-4217 currency code matching price; null when price is not applicable.');
            $table->timestamp('fetched_at')
                ->comment('When the scraper captured this listing snapshot; drives change detection windows.');
            $table->string('checksum')
                ->comment('Hash of content fields; compared against the previous snapshot to detect changes.');
            $table->timestamps();

            $table->unique(['app_id', 'version_id', 'locale'], 'app_store_listings_app_version_locale_unique');
            $table->index('version_id');
            $table->index(['app_id', 'locale', 'fetched_at']);
            $table->index('checksum');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_store_listings');
    }
};
