<?php

return [
    'minimum_withdrawal_amount' => 500,
    'payout_methods' => [
        'bank',
        'upi',
    ],
    'payout_provider' => env('AGENT_PAYOUT_PROVIDER', 'manual'),
];
