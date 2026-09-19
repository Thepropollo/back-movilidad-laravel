<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alerts', function (Blueprint $table) {
            $table->string('audience_role', 40)->nullable()->after('route')->index();
            $table->foreignId('user_id')->nullable()->after('audience_role')->constrained('users')->nullOnDelete();
            $table->json('detail')->nullable()->after('user_id');
        });

        Schema::table('document_signatures', function (Blueprint $table) {
            $table->string('signature_image_path', 255)->nullable()->after('key_fingerprint');
        });
    }

    public function down(): void
    {
        Schema::table('document_signatures', function (Blueprint $table) {
            $table->dropColumn('signature_image_path');
        });

        Schema::table('alerts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn(['audience_role', 'detail']);
        });
    }
};
