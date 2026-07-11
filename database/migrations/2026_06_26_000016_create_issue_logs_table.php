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
        Schema::create('issue_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained('vehicles')->onDelete('cascade');
            $table->foreignId('route_sheet_id')->nullable()->constrained('route_sheets')->onDelete('set null');
            $table->foreignId('reporting_driver_id')->constrained('users')->onDelete('cascade');
            $table->date('breakdown_date');
            $table->text('description');
            $table->string('status', 20)->default('pendiente'); // 'pendiente', 'en_revision', 'solventado'
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('issue_logs');
    }
};
