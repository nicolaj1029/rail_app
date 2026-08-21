<?php
declare(strict_types=1);

return [
    'currency' => 'EUR',
    'note' => 'Review bands only. Not legal caps under EU261.',
    'assistance_bands_by_airport_zone' => [
        'low' => [
            'meals' => ['min' => 10, 'max' => 25, 'manual_review_above' => 40],
            'hotel' => ['min' => 80, 'max' => 150, 'manual_review_above' => 220],
            'hotel_airport_transfer' => ['min' => 10, 'max' => 50, 'manual_review_above' => 80],
            'communication' => ['min' => 0, 'max' => 20, 'manual_review_above' => 30],
        ],
        'mid' => [
            'meals' => ['min' => 15, 'max' => 40, 'manual_review_above' => 60],
            'hotel' => ['min' => 120, 'max' => 300, 'manual_review_above' => 400],
            'hotel_airport_transfer' => ['min' => 20, 'max' => 100, 'manual_review_above' => 150],
            'communication' => ['min' => 0, 'max' => 20, 'manual_review_above' => 30],
        ],
        'high' => [
            'meals' => ['min' => 20, 'max' => 50, 'manual_review_above' => 75],
            'hotel' => ['min' => 180, 'max' => 400, 'manual_review_above' => 550],
            'hotel_airport_transfer' => ['min' => 30, 'max' => 150, 'manual_review_above' => 220],
            'communication' => ['min' => 0, 'max' => 20, 'manual_review_above' => 30],
        ],
        'hub' => [
            'meals' => ['min' => 20, 'max' => 60, 'manual_review_above' => 90],
            'hotel' => ['min' => 200, 'max' => 450, 'manual_review_above' => 650],
            'hotel_airport_transfer' => ['min' => 40, 'max' => 180, 'manual_review_above' => 260],
            'communication' => ['min' => 0, 'max' => 20, 'manual_review_above' => 30],
        ],
        'very_high' => [
            'meals' => ['min' => 25, 'max' => 70, 'manual_review_above' => 100],
            'hotel' => ['min' => 250, 'max' => 550, 'manual_review_above' => 750],
            'hotel_airport_transfer' => ['min' => 50, 'max' => 220, 'manual_review_above' => 320],
            'communication' => ['min' => 0, 'max' => 20, 'manual_review_above' => 30],
        ],
    ],
    'transport_bands_by_airport_zone' => [
        'low' => [
            'city_transfer' => ['min' => 10, 'max' => 100, 'manual_review_above' => 100],
            'airport_change_transfer' => ['min' => 20, 'max' => 200, 'manual_review_above' => 200],
            'rail_or_bus_transfer' => ['min' => 10, 'max' => 200, 'manual_review_above' => 200],
            'taxi_or_rideshare' => ['min' => 20, 'max' => 150, 'manual_review_above' => 150],
        ],
        'mid' => [
            'city_transfer' => ['min' => 20, 'max' => 150, 'manual_review_above' => 150],
            'airport_change_transfer' => ['min' => 40, 'max' => 300, 'manual_review_above' => 300],
            'rail_or_bus_transfer' => ['min' => 20, 'max' => 300, 'manual_review_above' => 300],
            'taxi_or_rideshare' => ['min' => 30, 'max' => 200, 'manual_review_above' => 200],
        ],
        'high' => [
            'city_transfer' => ['min' => 30, 'max' => 220, 'manual_review_above' => 220],
            'airport_change_transfer' => ['min' => 60, 'max' => 400, 'manual_review_above' => 400],
            'rail_or_bus_transfer' => ['min' => 30, 'max' => 350, 'manual_review_above' => 350],
            'taxi_or_rideshare' => ['min' => 40, 'max' => 280, 'manual_review_above' => 280],
        ],
        'hub' => [
            'city_transfer' => ['min' => 40, 'max' => 260, 'manual_review_above' => 260],
            'airport_change_transfer' => ['min' => 80, 'max' => 450, 'manual_review_above' => 450],
            'rail_or_bus_transfer' => ['min' => 40, 'max' => 400, 'manual_review_above' => 400],
            'taxi_or_rideshare' => ['min' => 50, 'max' => 320, 'manual_review_above' => 320],
        ],
        'very_high' => [
            'city_transfer' => ['min' => 50, 'max' => 300, 'manual_review_above' => 300],
            'airport_change_transfer' => ['min' => 100, 'max' => 550, 'manual_review_above' => 550],
            'rail_or_bus_transfer' => ['min' => 50, 'max' => 450, 'manual_review_above' => 450],
            'taxi_or_rideshare' => ['min' => 60, 'max' => 400, 'manual_review_above' => 400],
        ],
    ],
    'flight_ticket_bands' => [
        'regional_or_shorthaul' => [
            'min' => 100,
            'max' => 1500,
            'manual_review_above' => 1500,
        ],
        'longhaul' => [
            'min' => 300,
            'max' => 3000,
            'manual_review_above' => 3000,
        ],
    ],
];
