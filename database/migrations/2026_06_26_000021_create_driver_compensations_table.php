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
        Schema::create('driver_compensations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('route_sheet_id')->unique()->constrained('route_sheets')->onDelete('cascade');
            $table->foreignId('applied_rate_id')->constrained('rate_configurations')->onDelete('restrict');
            $table->decimal('allowances_amount', 10, 2)->default(0.00);
            $table->decimal('overtime_50_amount', 10, 2)->default(0.00);
            $table->decimal('overtime_100_amount', 10, 2)->default(0.00);
            $table->decimal('total_payout', 10, 2);
            $table->string('payment_receipt_url', 255)->nullable();
            $table->string('payment_status', 20)->default('pendiente_comprobante');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('driver_compensations');
    }
};
