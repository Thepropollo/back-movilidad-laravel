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
        Schema::create('mobilization_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requester_id')->constrained('users')->onDelete('cascade');
            $table->string('mobilization_type', 20); // 'interna', 'externa'
            $table->string('origin', 100)->default('MANTA');
            $table->string('destination', 100);
            $table->text('travel_reason');
            $table->date('departure_date');
            $table->date('return_date');
            $table->integer('estimated_days');
            $table->decimal('projected_cost', 10, 2);
            $table->string('status', 30)->default('pendiente'); // 'pendiente', 'aprobado_rectorado', 'aprobada', 'rechazada'
            $table->foreignId('rectorate_approver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mobilization_requests');
    }
};
