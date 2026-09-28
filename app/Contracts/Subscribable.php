<?php

namespace App\Contracts;

interface Subscribable
{
    /**
     * نوع المشترك.
     *
     * doctor
     * pharmacist
     * ambulance_driver
     */
    public function subscriptionType(): string;

    /**
     * user_id الخاص بالمشترك.
     */
    public function getSubscriptionUserId(): int;
}