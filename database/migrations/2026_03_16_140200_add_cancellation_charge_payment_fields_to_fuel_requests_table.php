<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fuel_requests')) {
            return;
        }

        Schema::table('fuel_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('fuel_requests', 'cancellation_charge_payment_status')) {
                $table->string('cancellation_charge_payment_status', 20)
                    ->default('none')
                    ->after('cancellation_charge');
            }

            if (! Schema::hasColumn('fuel_requests', 'cancellation_charge_payment_method')) {
                $table->string('cancellation_charge_payment_method', 20)
                    ->nullable()
                    ->after('cancellation_charge_payment_status');
            }

            if (! Schema::hasColumn('fuel_requests', 'cancellation_charge_paid_at')) {
                $table->timestamp('cancellation_charge_paid_at')
                    ->nullable()
                    ->after('cancellation_charge_payment_method');
            }

            if (! Schema::hasColumn('fuel_requests', 'cancellation_charge_payment_reference')) {
                $table->string('cancellation_charge_payment_reference')
                    ->nullable()
                    ->after('cancellation_charge_paid_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('fuel_requests')) {
            return;
        }

        Schema::table('fuel_requests', function (Blueprint $table) {
            foreach ([
                'cancellation_charge_payment_reference',
                'cancellation_charge_paid_at',
                'cancellation_charge_payment_method',
                'cancellation_charge_payment_status',
            ] as $column) {
                if (Schema::hasColumn('fuel_requests', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
