<?php

namespace Modules\Ambulance\Services;

use Modules\Ambulance\Models\AmbulanceCompany;

class AmbulanceCompanyService
{
    public function myCompany($user)
    {
        $company = AmbulanceCompany::where('user_id', $user->id)->first();

        if (!$company) {
            return [
                'has_company' => false
            ];
        }

        return [
            'has_company' => true,
            'company' => $company
        ];
    }

  public function createCompany($user, array $data)
{
    // // ❌ منع التكرار لنفس المستخدم
    // if (AmbulanceCompany::where('user_id', $user->id)->exists()) {
    //     return [
    //         'success' => false,
    //         'message' => 'You already have a company.'
    //     ];
    // }

    // ❌ منع تكرار الاسم في النظام كله
    if (AmbulanceCompany::where('name', $data['name'])->exists()) {
        return [
            'success' => false,
            'message' => 'Company name already exists.'
        ];
    }

    $company = AmbulanceCompany::create([
        'user_id' => $user->id,
        'governorate_id' => $data['governorate_id'],
        'name' => $data['name'],
        'license_number' => $data['license_number'],
        'phone' => $data['phone'],
        'email' => $user->email,
        'address' => $data['address'],
        'latitude' => $data['latitude'] ?? null,
        'longitude' => $data['longitude'] ?? null,
        'description' => $data['description'] ?? null,
        'work_from' => $data['work_from'] ?? null,
        'work_to' => $data['work_to'] ?? null,
        'is_24_hours' => $data['is_24_hours'] ?? false,
        'logo' => $data['logo'] ?? null,

        // 🚨 لازم موافقة الأدمن
        'status' => 'pending',
        'is_active' => false,
    ]);

    return [
        'success' => true,
        'message' => 'Company submitted successfully and is pending admin approval.',
        'company' => $company
    ];
}

     public function updateLocation($userId, array $data)
    {
        $company = AmbulanceCompany::where('user_id', $userId)->firstOrFail();

        $company->update([
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
        ]);

        return $company;
    }


  public function update($userId, array $data)
{
    $company = AmbulanceCompany::where('user_id', $userId)->firstOrFail();

    $company->update($data);

    return $company->fresh();
}
}