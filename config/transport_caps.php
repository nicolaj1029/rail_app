<?php
declare(strict_types=1);

return [
    'TransportCaps' => [
        'air' => [
            'engine' => [
                'meals' => [
                    'per_day_eur' => 80,
                    'breakfast_eur' => 20,
                    'lunch_eur' => 25,
                    'dinner_eur' => 35,
                ],
                'hotel' => [
                    'per_night_eur' => 180,
                    'high_cost_city_eur' => 250,
                    'max_nights' => 2,
                ],
                'hotel_transport' => [
                    'total_eur' => 100,
                    'per_trip_eur' => 50,
                ],
                'transfer' => [
                    'urban_eur' => 150,
                    'inter_airport_eur' => 300,
                ],
                'self_reroute' => [
                    'short_medium_haul_eur' => 1500,
                    'long_haul_eur' => 3000,
                    'max_multiplier_ticket_value' => 3,
                ],
            ],
            'jurisdictions' => [
                'EU261' => [
                    'care' => [
                        'type' => 'reasonable',
                        'notes' => 'No fixed monetary caps. Must be free and proportionate to waiting time.',
                    ],
                    'reroute' => [
                        'type' => 'comparable_transport',
                        'notes' => 'Earliest opportunity or later at passenger choice.',
                    ],
                    'transfer' => [
                        'type' => 'full_coverage',
                        'notes' => 'Airline must pay transfer between airports.',
                    ],
                    'compensation_eur' => [
                        'short_haul' => 250,
                        'medium_haul' => 400,
                        'long_haul' => 600,
                    ],
                    'downgrade_refund_pct' => [
                        'short_haul' => 30,
                        'medium_haul' => 50,
                        'long_haul' => 75,
                    ],
                ],
                'CA' => [
                    'care' => [
                        'type' => 'reasonable',
                        'notes' => 'Food, drink, hotel and transport required with no strict monetary cap.',
                    ],
                    'compensation_cad' => [
                        'large_carrier' => [
                            'low_delay' => 400,
                            'mid_delay' => 700,
                            'high_delay' => 1000,
                        ],
                        'small_carrier' => [
                            'low_delay' => 125,
                            'mid_delay' => 250,
                            'high_delay' => 500,
                        ],
                    ],
                    'denied_boarding_cad' => [
                        'low' => 900,
                        'mid' => 1800,
                        'high' => 2400,
                    ],
                ],
                'US' => [
                    'care' => [
                        'type' => 'airline_policy',
                        'notes' => 'No general federal obligation for meals or hotel on delays/cancellations.',
                    ],
                    'denied_boarding_usd' => [
                        'low' => 1075,
                        'high' => 2150,
                        'multiplier_low' => 2,
                        'multiplier_high' => 4,
                    ],
                ],
                'MONTREAL' => [
                    'delay_damage_sdr' => 6303,
                    'notes' => 'Documented delay damages only. Not a live care or reimbursement cap.',
                ],
            ],
        ],
    ],
];
