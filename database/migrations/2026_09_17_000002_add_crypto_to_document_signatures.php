<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_signatures', function (Blueprint $table) {
            $table->text('signed_payload')->nullable()->after('document_hash');
            $table->text('crypto_signature')->nullable()->after('signed_payload');
            $table->string('key_fingerprint', 32)->nullable()->after('crypto_signature');
        });
    }

    public function down(): void
    {
        Schema::table('document_signatures', function (Blueprint $table) {
            $table->dropColumn(['signed_payload', 'crypto_signature', 'key_fingerprint']);
        });
    }
};
