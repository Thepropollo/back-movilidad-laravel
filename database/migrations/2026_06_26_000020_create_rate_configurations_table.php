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
        Schema::create('rate_configurations', function (Blueprint $table) {
            $table->id();
            $table->string('rate_key', 50)->unique(); // 'viatico_diario', 'extra_50', 'extra_100'
            $table->decimal('rate_value', 10, 2);
            $table->timestamp('updated_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rate_configurations');
    }
};
