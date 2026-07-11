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
        Schema::create('workshop_work_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('issue_log_id')->nullable()->constrained('issue_logs')->onDelete('set null');
            $table->foreignId('vehicle_id')->constrained('vehicles')->onDelete('cascade');
            $table->foreignId('responsible_mechanic_id')->constrained('users')->onDelete('restrict');
            $table->foreignId('supervisor_id')->constrained('users')->onDelete('restrict');
            $table->string('maintenance_type', 20); // 'preventivo', 'correctivo', 'cambio_aceite'
            $table->text('work_details');
            $table->timestamp('entry_date');
            $table->timestamp('exit_date')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workshop_work_orders');
    }
};
