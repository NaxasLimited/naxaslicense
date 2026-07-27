<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activation_requests', function (Blueprint $table): void {
            $table->string('entitlement_fingerprint', 64)->nullable()->after('signed_entitlement');
        });
    }

    public function down(): void
    {
        Schema::table('activation_requests', fn (Blueprint $table) => $table->dropColumn('entitlement_fingerprint'));
    }
};
