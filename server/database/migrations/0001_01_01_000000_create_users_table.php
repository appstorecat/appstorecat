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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name')
                ->comment('Display name shown in UI; free-form, not unique.');
            $table->string('email')->unique()
                ->comment('Login identifier; unique across all users, lowercased on insert.');
            $table->timestamp('email_verified_at')->nullable()
                ->comment('Set when user confirms email via signed link; null = unverified.');
            $table->string('password')
                ->comment('Bcrypt hash of the user password (never plaintext).');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary()
                ->comment('Email address the reset was requested for; acts as PK (one active reset per email).');
            $table->string('token')
                ->comment('Hashed reset token sent to the user via email.');
            $table->timestamp('created_at')->nullable()
                ->comment('Issue time of the reset token; used to enforce expiry window.');
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary()
                ->comment('Opaque session identifier stored in the session cookie.');
            $table->foreignId('user_id')->nullable()->index()
                ->comment('FK -> users.id; null for guest sessions (pre-login).');
            $table->string('ip_address', 45)->nullable()
                ->comment('Last-seen client IP (IPv4 or IPv6); used for audit/security signals.');
            $table->text('user_agent')->nullable()
                ->comment('Last-seen User-Agent header; truncated/sanitized by Laravel.');
            $table->longText('payload')
                ->comment('Serialized session data (flash, auth, CSRF, etc.), base64-encoded.');
            $table->integer('last_activity')->index()
                ->comment('Unix timestamp of the last request on this session; drives session GC.');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
