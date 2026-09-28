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
        Schema::create('disputes', function (Blueprint $table) {
            $table->id();
            // صاحب الشكوى
            $table->foreignId('complainant_id')
                ->constrained('users')
                ->onDelete('cascade');

            // المشتكى عليه
            $table->foreignId('defendant_id')
                ->constrained('users')
                ->onDelete('cascade');

            // من قام بإدارة النزاع
            $table->foreignId('handled_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // عنوان الشكوى
            $table->string('subject');

            // وصف المشكلة
            $table->longText('description');

            // أولوية الشكوى
            $table->enum('priority', [
                'low',
                'medium',
                'high',
                'critical'
            ])->default('medium');

            // حالة الشكوى
            $table->enum('status', [
                'open',
                'in_progress',
                'waiting_response',
                'resolved',
                'rejected',
                'escalated',
                'closed'
            ])->default('open');

            // وقت الحل
            $table->timestamp('resolved_at')
                ->nullable();

            // هل تم التصعيد؟
            $table->boolean('is_escalated')
                ->default(false);
            $table->foreignId('doctor_id')->nullable()->constrained()->nullOnDelete();

            $table->foreignId('pharmacist_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            // Performance Indexes
            $table->index('status');

            $table->index('priority');

            $table->index('complainant_id');

            $table->index('defendant_id');

            $table->index('handled_by');

            $table->index(['status', 'priority']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('disputes');
    }
};
