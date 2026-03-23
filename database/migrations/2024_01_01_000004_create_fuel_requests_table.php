<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('fuel_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('agent_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('fuel_product_id')->constrained('fuel_products')->onDelete('restrict');
            $table->enum('status', ['pending','accepted','on_the_way','delivered','completed','cancelled'])->default('pending');
            $table->decimal('quantity_liters', 8, 2);
            $table->decimal('fuel_price_per_liter', 8, 2);
            $table->decimal('delivery_charge', 8, 2)->default(0);
            $table->decimal('total_amount', 10, 2);
            $table->enum('payment_method', ['upi','card','cod'])->default('cod');
            $table->enum('payment_status', ['pending','paid','failed'])->default('pending');
            $table->string('delivery_address')->nullable();
            $table->decimal('delivery_lat', 10, 7)->nullable();
            $table->decimal('delivery_lng', 10, 7)->nullable();
            $table->integer('estimated_delivery_minutes')->nullable();
            $table->decimal('distance_km', 8, 2)->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('fuel_requests'); }
};
