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
        Schema::create('service_stations', function (Blueprint $table) {
            $table->id();
            $table->string('commercial_name', 150);
            $table->string('ruc', 13)->unique();
            $table->string('address', 255);
            $table->boolean('active_agreement')->default(true);
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_stations');
    }
};
