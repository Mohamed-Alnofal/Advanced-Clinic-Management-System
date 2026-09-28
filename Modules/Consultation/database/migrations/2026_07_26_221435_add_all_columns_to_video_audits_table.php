<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('video_audits', function (Blueprint $table) {
            
            // ✅ إضافة الأعمدة مع التحقق من وجودها
            if (!Schema::hasColumn('video_audits', 'user_role')) {
                $table->string('user_role')->nullable()->after('user_id');
            }

            if (!Schema::hasColumn('video_audits', 'user_name')) {
                $table->string('user_name')->nullable()->after('user_role');
            }

            if (!Schema::hasColumn('video_audits', 'user_email')) {
                $table->string('user_email')->nullable()->after('user_name');
            }

            if (!Schema::hasColumn('video_audits', 'joined_at')) {
                $table->timestamp('joined_at')->nullable()->after('event');
            }

            if (!Schema::hasColumn('video_audits', 'left_at')) {
                $table->timestamp('left_at')->nullable()->after('joined_at');
            }

            if (!Schema::hasColumn('video_audits', 'duration_seconds')) {
                $table->integer('duration_seconds')->nullable()->after('left_at');
            }

            if (!Schema::hasColumn('video_audits', 'device_type')) {
                $table->string('device_type')->nullable()->after('user_agent');
            }

            if (!Schema::hasColumn('video_audits', 'browser')) {
                $table->string('browser')->nullable()->after('device_type');
            }

            // ✅ إضافة فهارس جديدة
            if (!Schema::hasIndex('video_audits', 'video_audits_user_name_index')) {
                $table->index('user_name');
            }

            if (!Schema::hasIndex('video_audits', 'video_audits_created_at_index')) {
                $table->index('created_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('video_audits', function (Blueprint $table) {
            $columns = [
                'user_role',
                'user_name',
                'user_email',
                'joined_at',
                'left_at',
                'duration_seconds',
                'device_type',
                'browser',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('video_audits', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};