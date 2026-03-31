<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('delivery_charges', function (Blueprint $table) {
            $table->id();
            $table->decimal('min_distance_km', 8, 2);
            $table->decimal('max_distance_km', 8, 2);
            $table->decimal('charge_amount', 8, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('delivery_charges'); }
};
