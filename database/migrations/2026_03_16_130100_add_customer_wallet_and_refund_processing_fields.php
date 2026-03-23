<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (! Schema::hasColumn('users', 'wallet_balance')) {
                    $table->decimal('wallet_balance', 10, 2)->default(0)->after('location');
                }
            });
        }

        if (Schema::hasTable('billings')) {
            Schema::table('billings', function (Blueprint $table) {
                if (Schema::hasColumn('billings', 'refundable_amount')) {
                    $table->decimal('refundable_amount', 10, 2)->nullable()->change();
                }

                if (Schema::hasColumn('billings', 'refund_status')) {
                    $table->string('refund_status', 20)->default('none')->change();
                }

                if (! Schema::hasColumn('billings', 'refund_processed_at')) {
                    $table->timestamp('refund_processed_at')->nullable()->after('refund_status');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'wallet_balance')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('wallet_balance');
            });
        }

        if (Schema::hasTable('billings') && Schema::hasColumn('billings', 'refund_processed_at')) {
            Schema::table('billings', function (Blueprint $table) {
                $table->dropColumn('refund_processed_at');
            });
        }
    }
};
