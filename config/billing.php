<?php

return [
    'plans' => [
        'individual_monthly' => [
            'price_id' => env('STRIPE_INDIVIDUAL_MONTHLY_PRICE_ID'),
            'credits_per_period' => 5,
        ],
        'individual_yearly' => [
            'price_id' => env('STRIPE_INDIVIDUAL_YEARLY_PRICE_ID'),
            'credits_per_period' => 60,
        ],
        'org_monthly' => [
            'price_id' => env('STRIPE_ORG_MONTHLY_PRICE_ID'),
            'member_limit' => 25,
            'assessments_per_period' => 50,
        ],
        'org_yearly' => [
            'price_id' => env('STRIPE_ORG_YEARLY_PRICE_ID'),
            'member_limit' => 25,
            'assessments_per_period' => 600,
        ],
    ],
];
