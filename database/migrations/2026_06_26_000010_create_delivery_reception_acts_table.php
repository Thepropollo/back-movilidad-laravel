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
        Schema::create('delivery_reception_acts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('route_sheet_id')->constrained('route_sheets')->onDelete('cascade');
            $table->foreignId('mechanic_or_guard_id')->constrained('users')->onDelete('restrict');
            $table->string('registration_type', 20); // 'salida', 'llegada'
            $table->string('fuel_level', 20); // '1/4', '1/2', '3/4', 'full'
            $table->integer('checkpoint_mileage');
            $table->text('general_observations')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delivery_reception_acts');
    }
};
