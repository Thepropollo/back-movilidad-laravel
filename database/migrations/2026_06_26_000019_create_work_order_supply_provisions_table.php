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
        Schema::create('work_order_supply_provisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained('workshop_work_orders')->onDelete('cascade');
            $table->foreignId('supply_id')->constrained('supply_inventories')->onDelete('cascade');
            $table->integer('quantity_used');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('work_order_supply_provisions');
    }
};
