<?php

return [
    'auto_cancel' => [
        'pending_after_minutes' => 5,
        'accepted_without_movement_minutes' => 10,
    ],
    'charges' => [
        'agent_compensation_rate' => 0.30,
    ],
    'refunds' => [
        'require_admin_approval' => true,
        'processor' => env('FUELMATE_REFUND_PROCESSOR', 'manual'),
    ],
    'fraud' => [
        'window_minutes' => 1440,
        'customer_max_cancellations' => 3,
        'agent_max_cancellations' => 5,
    ],
];
