<?php

return [
    'name' => 'Wallet',
    // When true, top-up credits the wallet immediately (no Stripe) — for local/demo only.
    'instant_topup' => filter_var(env('WALLET_INSTANT_TOPUP', false), FILTER_VALIDATE_BOOL),
];
