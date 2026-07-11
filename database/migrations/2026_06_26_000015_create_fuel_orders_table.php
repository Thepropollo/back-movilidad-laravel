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
        Schema::create('fuel_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_code', 50)->unique();
            $table->foreignId('route_sheet_id')->constrained('route_sheets')->onDelete('cascade');
            $table->foreignId('station_id')->constrained('service_stations')->onDelete('restrict');
            $table->foreignId('transport_chief_id')->constrained('users')->onDelete('restrict');
            $table->string('dispatched_fuel_type', 20);
            $table->decimal('authorized_gallons', 6, 2);
            $table->decimal('actual_dispatched_gallons', 6, 2)->nullable();
            $table->decimal('total_amount_paid', 10, 2)->nullable();
            $table->string('order_status', 20)->default('emitida'); // 'emitida', 'despachada', 'anulada'
            $table->timestamp('dispatch_date')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fuel_orders');
    }
};
