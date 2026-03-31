<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fuel_requests')) {
            Schema::table('fuel_requests', function (Blueprint $table) {
                if (Schema::hasColumn('fuel_requests', 'status')) {
                    $table->string('status', 30)->default('pending')->change();
                }

                if (! Schema::hasColumn('fuel_requests', 'is_cancelled')) {
                    $table->boolean('is_cancelled')->default(false)->after('status');
                }

                if (! Schema::hasColumn('fuel_requests', 'cancelled_by')) {
                    $table->enum('cancelled_by', ['customer', 'agent', 'admin', 'system'])
                        ->nullable()
                        ->after('is_cancelled');
                }

                if (Schema::hasColumn('fuel_requests', 'cancellation_reason')) {
                    $table->text('cancellation_reason')->nullable()->change();
                } else {
                    $table->text('cancellation_reason')->nullable()->after('cancelled_by');
                }

                if (! Schema::hasColumn('fuel_requests', 'cancelled_at')) {
                    $table->timestamp('cancelled_at')->nullable()->after('cancellation_reason');
                }

                if (! Schema::hasColumn('fuel_requests', 'cancellation_charge')) {
                    $table->decimal('cancellation_charge', 10, 2)->default(0)->after('cancelled_at');
                }

                if (! Schema::hasColumn('fuel_requests', 'agent_last_movement_at')) {
                    $table->timestamp('agent_last_movement_at')->nullable()->after('cancellation_charge');
                }
            });
        }

        DB::table('fuel_requests')
            ->where('status', 'completed')
            ->update(['status' => 'delivered']);

        DB::table('fuel_requests')
            ->where('status', 'cancelled')
            ->update([
                'is_cancelled' => true,
                'cancelled_by' => DB::raw("COALESCE(cancelled_by, 'admin')"),
                'cancelled_at' => DB::raw('COALESCE(cancelled_at, updated_at)'),
                'cancellation_charge' => DB::raw('COALESCE(cancellation_charge, 0)'),
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('fuel_requests')) {
            return;
        }

        Schema::table('fuel_requests', function (Blueprint $table) {
            if (Schema::hasColumn('fuel_requests', 'agent_last_movement_at')) {
                $table->dropColumn('agent_last_movement_at');
            }

            if (Schema::hasColumn('fuel_requests', 'cancellation_charge')) {
                $table->dropColumn('cancellation_charge');
            }

            if (Schema::hasColumn('fuel_requests', 'cancelled_at')) {
                $table->dropColumn('cancelled_at');
            }

            if (Schema::hasColumn('fuel_requests', 'cancelled_by')) {
                $table->dropColumn('cancelled_by');
            }

            if (Schema::hasColumn('fuel_requests', 'is_cancelled')) {
                $table->dropColumn('is_cancelled');
            }
        });
    }
};
