<?php

namespace Modules\Doctors\Services;

use Modules\Appointments\Models\Appointment;
use Modules\Doctors\Models\Doctor;
use willvincent\Rateable\Rating;

class DoctorReviewService
{
    /**
     * @return array{message: string, average_rating?: float, ratings_count?: int, my_rating?: int|null, can_rate?: bool, rating_breakdown?: array, status?: int}
     */
    public function rateDoctor(array $data, $user): array
    {
        if (! $user) {
            return ['message' => 'Unauthenticated', 'status' => 401];
        }

        $doctor = Doctor::findOrFail($data['doctor_id']);
        $rating = (int) $data['rating'];

        if ($rating < 1 || $rating > 5) {
            return ['message' => 'Invalid rating', 'status' => 422];
        }

        if (! $this->userHasCompletedVisit((int) $user->id, (int) $doctor->id)) {
            return [
                'message' => 'You can rate a doctor only after a completed clinic or video appointment.',
                'status' => 422,
            ];
        }

        $userId = (int) $user->id;
        $morphType = $doctor->getMorphClass();

        // Direct upsert — avoid Rateable::rateOnce Auth::id / morph edge cases with JWT.
        $existing = Rating::query()
            ->where('rateable_id', $doctor->id)
            ->where('user_id', $userId)
            ->where(function ($q) use ($morphType) {
                $q->where('rateable_type', $morphType)
                    ->orWhere('rateable_type', Doctor::class);
            })
            ->orderByDesc('id')
            ->first();

        if ($existing) {
            $existing->rating = $rating;
            $existing->comment = $data['comment'] ?? $existing->comment;
            $existing->rateable_type = $morphType;
            $existing->save();

            Rating::query()
                ->where('rateable_id', $doctor->id)
                ->where('user_id', $userId)
                ->where('id', '!=', $existing->id)
                ->delete();
        } else {
            $row = new Rating();
            $row->rating = $rating;
            $row->comment = $data['comment'] ?? null;
            $row->user_id = $userId;
            $row->rateable_type = $morphType;
            $row->rateable_id = $doctor->id;
            $row->save();
        }

        $meta = $this->ratingMeta($doctor->fresh(), $user);

        return [
            'message' => 'Rating submitted successfully',
            'average_rating' => $meta['average_rating'],
            'ratings_count' => $meta['ratings_count'],
            'rating_breakdown' => $meta['rating_breakdown'],
            'my_rating' => $rating,
            'can_rate' => $meta['can_rate'],
        ];
    }

    /**
     * @return array{can_rate: bool, my_rating: int|null, average_rating: float, ratings_count: int, rating_breakdown: array}
     */
    public function ratingMeta(Doctor $doctor, $user = null): array
    {
        $myRating = null;
        $canRate = false;

        if ($user) {
            $existing = $this->ratingsQuery($doctor)
                ->where('user_id', $user->id)
                ->orderByDesc('id')
                ->value('rating');
            $myRating = $existing !== null ? (int) $existing : null;
            $canRate = $this->userHasCompletedVisit((int) $user->id, (int) $doctor->id);
        }

        return [
            'can_rate' => $canRate,
            'my_rating' => $myRating,
            'average_rating' => $this->averageFromLatestPerUser($doctor),
            'ratings_count' => $this->uniqueRaterCount($doctor),
            'rating_breakdown' => $this->ratingBreakdown($doctor),
        ];
    }

    public function userHasCompletedVisit(int $userId, int $doctorId): bool
    {
        return Appointment::query()
            ->where('user_id', $userId)
            ->where('doctor_id', $doctorId)
            ->where('status', 'completed')
            ->exists();
    }

    private function ratingsQuery(Doctor $doctor)
    {
        $morphType = $doctor->getMorphClass();

        return Rating::query()
            ->where('rateable_id', $doctor->id)
            ->where(function ($q) use ($morphType) {
                $q->where('rateable_type', $morphType)
                    ->orWhere('rateable_type', Doctor::class);
            });
    }

    private function latestRatingIds(Doctor $doctor)
    {
        return $this->ratingsQuery($doctor)
            ->selectRaw('MAX(id) as id')
            ->groupBy('user_id')
            ->pluck('id');
    }

    /**
     * @return array{5: int, 4: int, 3: int, 2: int, 1: int}
     */
    private function ratingBreakdown(Doctor $doctor): array
    {
        $ids = $this->latestRatingIds($doctor);
        $breakdown = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
        if ($ids->isEmpty()) {
            return $breakdown;
        }

        $rows = Rating::query()
            ->whereIn('id', $ids)
            ->selectRaw('rating, COUNT(*) as total')
            ->groupBy('rating')
            ->pluck('total', 'rating');

        foreach ($rows as $star => $total) {
            $key = (int) $star;
            if (isset($breakdown[$key])) {
                $breakdown[$key] = (int) $total;
            }
        }

        return $breakdown;
    }

    private function averageFromLatestPerUser(Doctor $doctor): float
    {
        $ids = $this->latestRatingIds($doctor);
        if ($ids->isEmpty()) {
            return 0.0;
        }

        return round((float) Rating::query()->whereIn('id', $ids)->avg('rating'), 1);
    }

    private function uniqueRaterCount(Doctor $doctor): int
    {
        return (int) $this->ratingsQuery($doctor)->distinct()->count('user_id');
    }
}
