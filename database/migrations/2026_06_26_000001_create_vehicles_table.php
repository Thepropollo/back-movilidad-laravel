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
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('plate', 10)->unique();
            $table->string('brand', 50);
            $table->string('model', 50);
            $table->integer('year');
            $table->string('color', 30);
            $table->string('fuel_type', 20); // 'diesel', 'extra', 'super'
            $table->integer('current_mileage')->default(0);
            $table->integer('next_oil_change_mileage')->default(0);
            $table->string('operational_status', 30)->default('disponible'); // 'disponible', 'en_viaje', 'en_taller', 'inactivo'
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
