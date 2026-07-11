<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('act_tire_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('act_id')->constrained('delivery_reception_acts')->onDelete('cascade');
            $table->string('tire_position', 30); // 'delantera_derecha', 'repuesto', etc.
            $table->string('wear_condition', 10); // 'BUENO', 'REGULAR', 'MALO'
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('act_tire_states');
    }
};
