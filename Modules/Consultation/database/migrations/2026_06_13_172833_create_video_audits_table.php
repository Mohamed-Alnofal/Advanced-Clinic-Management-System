    <?php

    use Illuminate\Database\Migrations\Migration;
    use Illuminate\Database\Schema\Blueprint;
    use Illuminate\Support\Facades\Schema;

    return new class extends Migration
    {
        public function up(): void
        {
            Schema::create('video_audits', function (Blueprint $table) {

                        $table->id();

            // معرفات المكالمة
            $table->foreignId('consultation_id')
                ->constrained('consultations')
                ->cascadeOnDelete();

            $table->foreignId('appointment_id')
                ->constrained('appointments')
                ->cascadeOnDelete();


            // من قام بالحدث
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();


            // دور المستخدم
            $table->string('user_role')->nullable();


            // الحدث
            $table->string('event');


            // الحضور
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('left_at')->nullable();
            $table->integer('duration_seconds')->nullable();


            // معلومات تقنية
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();


            // تفاصيل إضافية
            $table->json('details')->nullable();


            $table->timestamps();


            // Indexes
            $table->index('consultation_id');
            $table->index('appointment_id');
            $table->index('user_id');
            $table->index('event');
            $table->index('user_role');
            });
        }

        public function down(): void
        {
            Schema::dropIfExists('video_audits');
        }
    };