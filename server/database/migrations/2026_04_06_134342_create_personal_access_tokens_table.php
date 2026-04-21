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
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->text('name')
                ->comment('Human-friendly label set by the user when creating the token.');
            $table->string('token', 64)->unique()
                ->comment('SHA-256 hash of the issued API token; plaintext is shown only once at creation.');
            $table->text('abilities')->nullable()
                ->comment('JSON array of granted Sanctum abilities (scopes); null = unrestricted ["*"].');
            $table->timestamp('last_used_at')->nullable()
                ->comment('Last time a request authenticated with this token; null until first use.');
            $table->timestamp('expires_at')->nullable()->index()
                ->comment('Optional expiry timestamp; null = never expires. Indexed for cleanup.');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('personal_access_tokens');
    }
};
