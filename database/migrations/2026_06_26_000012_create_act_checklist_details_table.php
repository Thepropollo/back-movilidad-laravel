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
        Schema::create('act_checklist_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('act_id')->constrained('delivery_reception_acts')->onDelete('cascade');
            $table->foreignId('component_id')->constrained('checklist_inventory_components')->onDelete('cascade');
            $table->string('physical_condition', 10); // 'BUENO', 'REGULAR', 'MALO'
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('act_checklist_details');
    }
};
