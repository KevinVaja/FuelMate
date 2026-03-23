<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained('fuel_requests')->cascadeOnDelete();
            $table->enum('billing_status', ['estimated', 'final', 'paid', 'settled'])->default('estimated');
            $table->decimal('fuel_price_per_liter', 10, 2);
            $table->decimal('fuel_quantity', 10, 2);
            $table->decimal('fuel_total', 10, 2);
            $table->decimal('delivery_charge', 10, 2)->default(0);
            $table->decimal('platform_fee', 10, 2)->default(0);
            $table->decimal('gst_percent', 5, 2)->default(18.00);
            $table->decimal('gst_amount', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->decimal('agent_commission_percent', 5, 2)->default(3.00);
            $table->decimal('agent_earning', 10, 2)->default(0);
            $table->decimal('admin_commission', 10, 2)->default(0);
            $table->decimal('coupon_discount', 10, 2)->nullable();
            $table->decimal('surge_pricing_multiplier', 5, 2)->default(1.00);
            $table->decimal('refundable_amount', 10, 2)->nullable();
            $table->decimal('refunded_amount', 10, 2)->default(0);
            $table->enum('refund_status', ['none', 'pending', 'refunded'])->nullable()->default('none');
            $table->enum('settlement_status', ['pending', 'approved', 'paid_out'])->default('pending');
            $table->string('payment_reference')->nullable();
            $table->string('payment_gateway')->nullable();
            $table->json('payment_webhook_payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billings');
    }
};
