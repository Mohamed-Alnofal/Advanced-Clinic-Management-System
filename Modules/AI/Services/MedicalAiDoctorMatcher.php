<?php

namespace Modules\AI\Services;

use App\Support\PublicMedia;
use Modules\Core\Models\specializations;
use Modules\Doctors\Models\Doctor;
use Modules\Doctors\Services\DoctorReviewService;
use Throwable;

/**
 * Maps an AI specialty hint onto real catalog specializations and listed doctors.
 * Additive helper — does not change booking or search logic.
 */
class MedicalAiDoctorMatcher
{
    /**
     * @return array{
     *   specialization: ?array{id: int, name: string},
     *   doctors: list<array<string, mixed>>
     * }
     */
    public function suggest(?string $specialtyHint, string $message = '', int $limit = 5): array
    {
        $empty = ['specialization' => null, 'doctors' => []];

        try {
            $specs = specializations::query()->orderBy('name')->get(['id', 'name']);
        } catch (Throwable) {
            return $empty;
        }

        if ($specs->isEmpty()) {
            return $empty;
        }

        $matched = $this->bestSpecialization($specs, $specialtyHint, $message);
        $query = Doctor::query()
            ->with(['user', 'specialization', 'clinics.medicalCenter'])
            ->listedForPatients();

        if ($matched !== null) {
            $query->where('specialization_id', $matched->id);
        } else {
            // No specialty lock: do not guess random doctors.
            return $empty;
        }

        try {
            $doctors = $query->limit(max(1, min($limit, 8)))->get();
        } catch (Throwable) {
            return [
                'specialization' => [
                    'id' => (int) $matched->id,
                    'name' => (string) $matched->name,
                ],
                'doctors' => [],
            ];
        }

        $reviews = app(DoctorReviewService::class);
        $payload = [];
        foreach ($doctors as $doctor) {
            $meta = $reviews->ratingMeta($doctor);
            $clinic = $doctor->clinics->first();
            $facility = $clinic?->medicalCenter;
            $payload[] = [
                'id' => (int) $doctor->id,
                'name' => (string) ($doctor->user?->name ?? ''),
                'image_url' => PublicMedia::url($doctor->image),
                'specialization' => (string) ($doctor->specialization?->name ?? $matched->name),
                'specialization_id' => (int) $doctor->specialization_id,
                'experience_years' => $doctor->experience_years !== null ? (int) $doctor->experience_years : null,
                'price' => $doctor->price !== null ? (string) $doctor->price : null,
                'bio' => $doctor->bio ? mb_substr((string) $doctor->bio, 0, 180) : null,
                'is_verified' => (bool) $doctor->is_verified,
                'average_rating' => $meta['average_rating'] ?? 0,
                'ratings_count' => $meta['ratings_count'] ?? 0,
                'clinic_name' => $facility?->name ?? $doctor->clinic_name,
                'clinic_address' => $facility?->address ?? $doctor->clinic_address,
            ];
        }

        usort($payload, function (array $a, array $b) {
            $rating = ($b['average_rating'] <=> $a['average_rating']);
            if ($rating !== 0) {
                return $rating;
            }

            return ((int) ($b['experience_years'] ?? 0)) <=> ((int) ($a['experience_years'] ?? 0));
        });

        return [
            'specialization' => [
                'id' => (int) $matched->id,
                'name' => (string) $matched->name,
            ],
            'doctors' => array_values($payload),
        ];
    }

    /**
     * Catalog names injected into the LLM prompt so specialty_suggested stays grounded.
     */
    public function catalogPromptBlock(): string
    {
        try {
            $names = specializations::query()
                ->orderBy('name')
                ->pluck('name')
                ->filter()
                ->unique()
                ->take(40)
                ->values();
        } catch (Throwable) {
            return '';
        }

        if ($names->isEmpty()) {
            return '';
        }

        return "Available clinic specializations (use an exact name in specialty_suggested when recommending a doctor):\n- "
            .$names->implode("\n- ");
    }

    /**
     * @param  \Illuminate\Support\Collection<int, specializations>  $specs
     */
    private function bestSpecialization($specs, ?string $specialtyHint, string $message): ?specializations
    {
        $haystack = mb_strtolower(trim(($specialtyHint ?? '').' '.$message));
        if ($haystack === '') {
            return null;
        }

        $best = null;
        $bestScore = 0;

        foreach ($specs as $spec) {
            $score = $this->scoreSpecialization((string) $spec->name, $haystack, $specialtyHint);
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $spec;
            }
        }

        return $bestScore >= 8 ? $best : null;
    }

    private function scoreSpecialization(string $specName, string $haystack, ?string $hint): int
    {
        $name = mb_strtolower(trim($specName));
        if ($name === '') {
            return 0;
        }

        $score = 0;
        $hintLower = mb_strtolower(trim((string) $hint));

        if ($hintLower !== '' && ($hintLower === $name || str_contains($hintLower, $name) || str_contains($name, $hintLower))) {
            $score += 40;
        }
        if (str_contains($haystack, $name)) {
            $score += 20;
        }

        foreach ($this->tokens($name) as $token) {
            if (mb_strlen($token) >= 3 && str_contains($haystack, $token)) {
                $score += 10;
            }
        }

        foreach ($this->aliasGroups() as $group) {
            $specHits = 0;
            $hayHits = 0;
            foreach ($group as $alias) {
                if (str_contains($name, $alias)) {
                    $specHits++;
                }
                if (str_contains($haystack, $alias)) {
                    $hayHits++;
                }
            }
            if ($specHits > 0 && $hayHits > 0) {
                $score += 18;
            }
        }

        return $score;
    }

    /** @return list<string> */
    private function tokens(string $name): array
    {
        $parts = preg_split('/[\s\/,_-]+/u', $name) ?: [];
        $out = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part !== '') {
                $out[] = $part;
            }
        }

        return $out;
    }

    /** @return list<list<string>> */
    private function aliasGroups(): array
    {
        return [
            ['cardiolog', 'cardiac', 'heart', 'قلب', 'قلبي', 'قلبية'],
            ['dermatolog', 'derma', 'skin', 'جلد', 'جلدية'],
            ['pediatr', 'child', 'أطفال', 'طفل', 'طفولة'],
            ['orthop', 'bone', 'عظام', 'orthopedic'],
            ['ophthalm', 'eye', 'عيون', 'عين', 'بصري'],
            ['oto', 'ent', 'أنف', 'أذن', 'حنجرة'],
            ['gyne', 'obstet', 'نساء', 'توليد', 'نسائية'],
            ['neurolog', 'neuro', 'دماغ', 'أعصاب', 'عصبي'],
            ['psych', 'نفس', 'نفسية'],
            ['urolog', 'مسالك', 'بولية'],
            ['gastro', 'هضم', 'معدة', 'جهاز هضمي'],
            ['endocrin', 'سكر', 'غدد', 'diabetes'],
            ['pulmon', 'chest', 'رئة', 'تنفسي', 'صدر'],
            ['internal', 'باطن', 'عامة', 'general'],
            ['dent', 'أسنان', 'فم'],
            ['family', 'أسرة', 'عائلي'],
            ['ent', 'انف'],
        ];
    }
}
