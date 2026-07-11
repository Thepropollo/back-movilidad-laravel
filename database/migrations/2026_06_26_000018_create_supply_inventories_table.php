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
        Schema::create('supply_inventories', function (Blueprint $table) {
            $table->id();
            $table->string('supply_name', 100); // 'Aceite 15W40', 'Filtro de Aceite'
            $table->integer('current_stock')->default(0);
            $table->string('measurement_unit', 20);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supply_inventories');
    }
};
