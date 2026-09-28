<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prescription_dispensings', function (Blueprint $table) {

            $table->id();

            $table->foreignId('prescription_id')
                ->constrained('prescriptions')
                ->cascadeOnDelete();

            /*
             * إذا كان جدول pharmacies موجوداً باسم مختلف،
             * غير هذا الحقل بما يناسب مشروعك.
             */
            $table->unsignedBigInteger('pharmacy_id');

            /*
             * المستخدم الذي قام بالصرف.
             */
            $table->foreignId('pharmacist_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->timestamp('dispensed_at');

            $table->string('status', 30)
                ->default('dispensed');

            /*
             * نسخة PDF التي تم اعتمادها عند الصرف.
             */
            $table->string('archived_pdf_path')
                ->nullable();

            /*
             * SHA-256 للملف المؤرشف.
             */
            $table->string('pdf_hash', 64)
                ->nullable();

            $table->timestamps();

            $table->index('prescription_id');
            $table->index('pharmacy_id');
            $table->index('pharmacist_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prescription_dispensings');
    }
};