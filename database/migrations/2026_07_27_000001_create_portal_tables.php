<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $t) => [$t->boolean('is_admin')->default(false), $t->boolean('is_active')->default(true), $t->dateTime('last_login_at')->nullable()]);
        Schema::create('products', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('slug')->unique();
            $t->string('current_version')->nullable();
            $t->string('status');
            $t->timestamps();
        });
        Schema::create('product_editions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $t->string('name');
            $t->string('slug');
            $t->string('license_type');
            $t->unsignedSmallInteger('production_domain_limit');
            $t->boolean('update_entitlement');
            $t->boolean('support_entitlement');
            $t->unsignedSmallInteger('support_duration_months')->nullable();
            $t->string('status');
            $t->timestamps();
            $t->unique(['product_id', 'slug']);
        });
        Schema::create('customers', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('email')->index();
            $t->string('company')->nullable();
            $t->string('status')->default('active');
            $t->text('notes')->nullable();
            $t->timestamps();
        });
        Schema::create('licenses', function (Blueprint $t) {
            $t->id();
            $t->string('license_id')->unique();
            $t->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $t->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $t->foreignId('product_edition_id')->constrained('product_editions')->restrictOnDelete();
            $t->string('status');
            $t->string('license_type');
            $t->unsignedSmallInteger('production_domain_limit');
            $t->boolean('update_entitlement');
            $t->boolean('support_entitlement');
            $t->dateTime('support_expires_at')->nullable();
            $t->dateTime('issued_at');
            $t->dateTime('expires_at')->nullable();
            $t->dateTime('suspended_at')->nullable();
            $t->dateTime('revoked_at')->nullable();
            $t->text('revocation_reason')->nullable();
            $t->timestamps();
        });
        Schema::create('activation_requests', function (Blueprint $t) {
            $t->id();
            $t->uuid('request_id')->unique();
            $t->string('request_token_hash');
            $t->string('request_token_prefix', 8);
            $t->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $t->foreignId('product_edition_id')->constrained('product_editions')->restrictOnDelete();
            $t->foreignId('license_id')->nullable()->constrained('licenses')->nullOnDelete();
            $t->uuid('installation_uuid');
            $t->string('normalized_domain');
            $t->string('environment', 32);
            $t->string('application_version', 64);
            $t->string('nonce_hash');
            $t->string('status');
            $t->dateTime('expires_at');
            $t->dateTime('approved_at')->nullable();
            $t->dateTime('rejected_at')->nullable();
            $t->dateTime('completed_at')->nullable();
            $t->string('failure_code')->nullable();
            $t->string('safe_failure_message')->nullable();
            $t->text('signed_entitlement')->nullable();
            $t->timestamps();
        });
        Schema::create('license_activations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('license_id')->constrained('licenses')->restrictOnDelete();
            $t->foreignId('activation_request_id')->unique()->restrictOnDelete();
            $t->uuid('installation_uuid');
            $t->string('normalized_domain');
            $t->string('domain_hash');
            $t->string('environment');
            $t->string('status');
            $t->dateTime('activated_at');
            $t->dateTime('deactivated_at')->nullable();
            $t->timestamps();
            $t->unique(['license_id', 'installation_uuid']);
        });
        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('action');
            $t->string('entity_type');
            $t->string('entity_id');
            $t->string('safe_summary');
            $t->uuid('correlation_id');
            $t->string('ip_address', 45)->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['audit_logs', 'license_activations', 'activation_requests', 'licenses', 'customers', 'product_editions', 'products'] as $x) {
            Schema::dropIfExists($x);
        }Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['is_admin', 'is_active', 'last_login_at']));
    }
};
