<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('generated_documents', function (Blueprint $table) {
            $table->string('file_hash', 64)->nullable()->after('mime_type');
        });

        Schema::create('document_signatures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('generated_documents')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('slot', 40);
            $table->string('slot_label', 80);
            $table->string('document_hash', 64);
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('signed_at')->useCurrent();
            $table->unique(['document_id', 'slot']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_signatures');

        Schema::table('generated_documents', function (Blueprint $table) {
            $table->dropColumn('file_hash');
        });
    }
};
