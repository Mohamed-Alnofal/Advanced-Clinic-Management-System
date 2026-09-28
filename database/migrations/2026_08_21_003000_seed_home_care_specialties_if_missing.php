<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('home_care_specialties')) {
            return;
        }

        $now = now();
        $rows = [
            ['name' => 'تمريض منزلي', 'slug' => 'nursing', 'description' => 'رعاية تمريضية منزلية'],
            ['name' => 'علاج طبيعي', 'slug' => 'physiotherapy', 'description' => 'جلسات علاج طبيعي في المنزل'],
            ['name' => 'رعاية مسنين', 'slug' => 'elderly-care', 'description' => 'رعاية كبار السن في المنزل'],
            ['name' => 'تمريض أطفال', 'slug' => 'pediatric-nursing', 'description' => 'رعاية تمريضية للأطفال في المنزل'],
            ['name' => 'رعاية ما بعد العمليات', 'slug' => 'post-surgery', 'description' => 'رعاية ما بعد العمليات الجراحية'],
            ['name' => 'علاج تنفسي', 'slug' => 'respiratory-therapy', 'description' => 'علاج تنفسي منزلي'],
            ['name' => 'رعاية مرضى السكري', 'slug' => 'diabetes-care', 'description' => 'رعاية مرضى السكري في المنزل'],
            ['name' => 'رعاية مرضى الضغط', 'slug' => 'hypertension-care', 'description' => 'رعاية مرضى ارتفاع الضغط'],
            ['name' => 'رعاية صحية عامة', 'slug' => 'general-care', 'description' => 'رعاية صحية عامة في المنزل'],
        ];

        foreach ($rows as $row) {
            $exists = DB::table('home_care_specialties')
                ->where('slug', $row['slug'])
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('home_care_specialties')->insert([
                'name' => $row['name'],
                'slug' => $row['slug'],
                'description' => $row['description'],
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Keep seeded specialties — safe for production.
    }
};
