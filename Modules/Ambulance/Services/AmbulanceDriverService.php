<?php

namespace Modules\Ambulance\Services;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Collection;
use Modules\Ambulance\Models\AmbulanceDriver;
use Modules\Ambulance\Models\AmbulanceCompany;
use Modules\Ambulance\Models\EmergencyRequestOffer;
class AmbulanceDriverService
{
    public function index($companyId)
    {
        return AmbulanceDriver::where(
            'ambulance_company_id',
            $companyId
        )->get();
    }
public function store(int $userId, array $data)
{
    $company = AmbulanceCompany::where('id', $data['ambulance_company_id'])
        ->where('user_id', $userId)
        ->first();

    if (! $company) {
        abort(403, 'You are not allowed to add drivers to this company.');
    }

    return AmbulanceDriver::create([
        'ambulance_company_id' => $company->id,
        'name' => $data['name'],
        'phone' => $data['phone'],
        'email' => $data['email'],
        'password' => Hash::make($data['password']),
    ]);
}
    public function update(AmbulanceDriver $driver, array $data)
    {
        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $driver->update($data);

        return $driver->fresh();
    }

    public function destroy(AmbulanceDriver $driver)
    {
        $driver->delete();

        return [
            'message' => 'Driver deleted successfully.'
        ];
    }

    /**
     * Pending offers shown in GET /api/driver/requests.
     */
    public function pendingRequestOffers(int $driverId): Collection
    {
        return EmergencyRequestOffer::query()
            ->with(['emergencyRequest.user:id,name,phone'])
            ->where('ambulance_driver_id', $driverId)
            ->where('status', 'offered')
            ->whereHas('emergencyRequest', fn ($q) => $q->where('status', 'pending'))
            ->orderByDesc('offered_at')
            ->get();
    }
}