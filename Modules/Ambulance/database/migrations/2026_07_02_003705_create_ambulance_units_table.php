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
        Schema::create('ambulance_units', function (Blueprint $table) {

        $table->id();

        $table->foreignId('ambulance_company_id')
            ->constrained()
            ->cascadeOnDelete();

    $table->foreignId('ambulance_driver_id')
        ->nullable()
        ->constrained()
        ->nullOnDelete();
        
        // رقم اللوحة
        $table->string('plate_number')->unique();

       

        // حالة السيارة
        $table->enum('status', [
            'available',
            'busy',
            'offline'
        ])->default('offline');

        // الموقع الحالي
        $table->decimal('latitude', 10, 7)->nullable();
        $table->decimal('longitude', 10, 7)->nullable();

        // آخر تحديث للموقع
        $table->timestamp('last_location_update')->nullable();
        $table->string('pairing_code')->nullable();
        $table->timestamp('pairing_expires_at')->nullable();
        $table->string('device_token')->nullable();
        $table->boolean('is_connected')->default(false);
        // هل السيارة مفعلة
        $table->boolean('is_active')->default(true);

        $table->timestamps();
    });
    }

        /**
         * Reverse the migrations.
         */
        public function down(): void
        {
            Schema::dropIfExists('ambulance_units');
        }
    };
