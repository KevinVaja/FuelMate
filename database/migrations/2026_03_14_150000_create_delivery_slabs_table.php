<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('delivery_slabs', function (Blueprint $table) {
            $table->id();
            $table->decimal('min_km', 8, 2);
            $table->decimal('max_km', 8, 2);
            $table->decimal('charge', 8, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_slabs');
    }
};
