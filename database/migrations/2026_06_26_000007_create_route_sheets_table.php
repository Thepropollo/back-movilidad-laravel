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
        Schema::create('route_sheets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->unique()->constrained('mobilization_requests')->onDelete('cascade');
            $table->foreignId('vehicle_id')->constrained('vehicles')->onDelete('restrict');
            $table->foreignId('driver_id')->constrained('drivers')->onDelete('restrict');
            $table->foreignId('transport_chief_id')->constrained('users')->onDelete('restrict');
            $table->integer('initial_mileage')->nullable();
            $table->integer('final_mileage')->nullable();
            $table->string('trip_status', 30)->default('programado'); // 'programado', 'en_ruta', 'pendiente_feedback', 'finalizado'
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('route_sheets');
    }
};
