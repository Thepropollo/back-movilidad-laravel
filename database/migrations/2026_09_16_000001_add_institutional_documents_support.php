<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mobilization_requests', function (Blueprint $table) {
            $table->unsignedInteger('occupant_count')->default(1)->after('travel_reason');
            $table->string('communication_number', 80)->nullable()->after('occupant_count');
            $table->string('activity_type', 50)->nullable()->after('communication_number');
            $table->string('academic_program', 150)->nullable()->after('activity_type');
            $table->unsignedInteger('public_servants_count')->default(0)->after('academic_program');
        });

        Schema::table('vehicles', function (Blueprint $table) {
            $table->string('registration_number', 40)->nullable()->after('plate');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('job_title', 120)->nullable()->after('faculty_institution');
        });

        Schema::create('generated_documents', function (Blueprint $table) {
            $table->id();
            $table->string('document_type', 40);
            $table->string('source', 20)->default('generated');
            $table->foreignId('request_id')->nullable()->constrained('mobilization_requests')->nullOnDelete();
            $table->unsignedBigInteger('work_order_id')->nullable();
            $table->unsignedBigInteger('issue_log_id')->nullable();
            $table->string('file_path', 255);
            $table->string('original_filename', 180);
            $table->string('mime_type', 80)->default('application/pdf');
            $table->foreignId('generated_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generated_documents');

        Schema::table('mobilization_requests', function (Blueprint $table) {
            $table->dropColumn([
                'occupant_count',
                'communication_number',
                'activity_type',
                'academic_program',
                'public_servants_count',
            ]);
        });

        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn('registration_number');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('job_title');
        });
    }
};
