<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\Ambulance\Models\EmergencyRequestOffer;
use Modules\Ambulance\Services\AmbulanceDispatchService;

class ExpireAmbulanceOffersCommand extends Command
{
    protected $signature = 'ambulance:expire-offers {--minutes=2}';

    protected $description = 'Expire stale ambulance offers and re-offer to the next nearest unit';

    public function handle(AmbulanceDispatchService $dispatch): int
    {
        $minutes = max(1, (int) $this->option('minutes'));
        $stale = EmergencyRequestOffer::query()
            ->where('status', 'offered')
            ->where('offered_at', '<', now()->subMinutes($minutes))
            ->with('emergencyRequest')
            ->orderBy('id')
            ->limit(50)
            ->get();

        $count = 0;
        foreach ($stale as $offer) {
            $request = $offer->emergencyRequest;
            if (! $request || $request->status !== 'pending') {
                $offer->update([
                    'status' => 'expired',
                    'responded_at' => now(),
                ]);
                $count++;

                continue;
            }

            $offer->update([
                'status' => 'expired',
                'responded_at' => now(),
            ]);

            $dispatch->offerToNearest($request, [(int) $offer->ambulance_unit_id]);
            $count++;
        }

        // Critical: SOS created while all units were offline stays pending with ZERO offers.
        // Keep redispatching until expire-stale-requests closes the request.
        $redispatched = $dispatch->redispatchPendingWithoutOffers();

        $this->info("Processed {$count} stale offer(s), redispatched {$redispatched} orphan pending request(s).");

        return self::SUCCESS;
    }
}
