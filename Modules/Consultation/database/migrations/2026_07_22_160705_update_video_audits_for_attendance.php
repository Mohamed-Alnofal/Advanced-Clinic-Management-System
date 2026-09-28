<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('video_audits', function (Blueprint $table) {
            if (! Schema::hasColumn('video_audits', 'user_role')) {
                $table->string('user_role')->nullable()->after('user_id');
            }

            if (! Schema::hasColumn('video_audits', 'joined_at')) {
                $table->timestamp('joined_at')->nullable()->after('event');
            }

            if (! Schema::hasColumn('video_audits', 'left_at')) {
                $table->timestamp('left_at')->nullable()->after('joined_at');
            }

            if (! Schema::hasColumn('video_audits', 'duration_seconds')) {
                $table->integer('duration_seconds')->nullable()->after('left_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('video_audits', function (Blueprint $table) {
            $columns = collect(['user_role', 'joined_at', 'left_at', 'duration_seconds'])
                ->filter(fn (string $column) => Schema::hasColumn('video_audits', $column))
                ->all();

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
