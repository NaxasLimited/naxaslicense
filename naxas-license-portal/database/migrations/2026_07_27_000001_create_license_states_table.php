<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('license_states', function (Blueprint $table): void {
            $table->id();
            $table->uuid('installation_uuid')->unique();
            $table->uuid('request_id')->nullable()->unique();
            $table->text('request_token')->nullable();
            $table->string('request_status')->nullable();
            $table->timestamp('request_expires_at')->nullable();
            $table->longText('signed_license')->nullable();
            $table->longText('entitlement')->nullable();
            $table->string('entitlement_fingerprint', 64)->nullable();
            $table->string('last_error_code')->nullable();
            $table->string('last_safe_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('license_states');
    }
};
