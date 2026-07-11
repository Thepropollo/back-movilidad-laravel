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
        Schema::create('route_sheet_stops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('route_sheet_id')->constrained('route_sheets')->onDelete('cascade');
            $table->string('visited_canton', 100);
            $table->timestamp('arrival_time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('route_sheet_stops');
    }
};
