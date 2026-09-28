<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\HomeCare\Models\HomeCareSpecialty;

class HomeCareSpecialtySeeder extends Seeder
{
    public function run(): void
    {
        $specialties = [
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

        foreach ($specialties as $specialty) {
            HomeCareSpecialty::firstOrCreate(
                ['slug' => $specialty['slug']],
                [
                    'name' => $specialty['name'],
                    'description' => $specialty['description'],
                    'is_active' => true,
                ]
            );
        }

        $this->command?->info('Home care specialties seeded successfully!');
    }
}
