<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('admins') && ! Schema::hasColumn('admins', 'wallet_balance')) {
            Schema::table('admins', function (Blueprint $table) {
                $table->decimal('wallet_balance', 10, 2)->default(0)->after('user_id');
            });
        }

        if (Schema::hasTable('fuel_requests')) {
            if (! Schema::hasColumn('fuel_requests', 'payment_method')) {
                Schema::table('fuel_requests', function (Blueprint $table) {
                    $table->string('payment_method', 20)->default('cod')->after('total_amount');
                });
            } else {
                Schema::table('fuel_requests', function (Blueprint $table) {
                    $table->string('payment_method', 20)->default('cod')->change();
                });
            }

            if (! Schema::hasColumn('fuel_requests', 'payment_status')) {
                Schema::table('fuel_requests', function (Blueprint $table) {
                    $table->string('payment_status', 20)->default('pending')->after('payment_method');
                });
            } else {
                Schema::table('fuel_requests', function (Blueprint $table) {
                    $table->string('payment_status', 20)->default('pending')->change();
                });
            }
        }

        DB::table('fuel_requests')
            ->whereIn('payment_method', ['upi', 'card'])
            ->update(['payment_method' => 'online']);

        if (Schema::hasTable('payments')) {
            DB::table('payments')
                ->whereIn('paymentmode', ['upi', 'card'])
                ->update(['paymentmode' => 'online']);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('admins') && Schema::hasColumn('admins', 'wallet_balance')) {
            Schema::table('admins', function (Blueprint $table) {
                $table->dropColumn('wallet_balance');
            });
        }

        if (Schema::hasTable('fuel_requests') && Schema::hasColumn('fuel_requests', 'payment_method')) {
            Schema::table('fuel_requests', function (Blueprint $table) {
                $table->enum('payment_method', ['upi', 'card', 'cod'])->default('cod')->change();
            });
        }

        if (Schema::hasTable('fuel_requests') && Schema::hasColumn('fuel_requests', 'payment_status')) {
            Schema::table('fuel_requests', function (Blueprint $table) {
                $table->enum('payment_status', ['pending', 'paid', 'failed'])->default('pending')->change();
            });
        }

        DB::table('fuel_requests')
            ->where('payment_method', 'online')
            ->update(['payment_method' => 'card']);

        if (Schema::hasTable('payments')) {
            DB::table('payments')
                ->where('paymentmode', 'online')
                ->update(['paymentmode' => 'card']);
        }
    }
};
