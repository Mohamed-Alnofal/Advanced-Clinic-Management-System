<?php

namespace Modules\Stripe\Http\Controllers;

use Illuminate\Http\Request;

/**
 * @deprecated Use Modules\Wallet webhook (POST /api/stripe/webhook → WalletTopUpService).
 * This controller must not credit wallets — it caused TOCTOU double-credit.
 */
class StripeWebhookController
{
    public function handle(Request $request)
    {
        return response()->json([
            'error' => 'Deprecated webhook. Configure Stripe to POST /api/stripe/webhook (Wallet module).',
        ], 410);
    }

    public function handleWebhook(Request $request)
    {
        return $this->handle($request);
    }
}
