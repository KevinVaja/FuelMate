<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'email_verified_at')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->timestamp('email_verified_at')->nullable()->after('email');
            });
        }

        if (! Schema::hasColumn('fuel_requests', 'delivery_handoff_token')) {
            Schema::table('fuel_requests', function (Blueprint $table): void {
                $table->string('delivery_handoff_token', 80)->nullable()->after('delivery_otp');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('fuel_requests', 'delivery_handoff_token')) {
            Schema::table('fuel_requests', function (Blueprint $table): void {
                $table->dropColumn('delivery_handoff_token');
            });
        }

        if (Schema::hasColumn('users', 'email_verified_at')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('email_verified_at');
            });
        }
    }
};
