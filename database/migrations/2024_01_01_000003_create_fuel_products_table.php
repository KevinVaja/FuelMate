<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('fuel_products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('fuel_type', ['petrol', 'diesel', 'premium_petrol', 'premium_diesel']);
            $table->decimal('price_per_liter', 8, 2);
            $table->boolean('is_available')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('fuel_products'); }
};
