<?php


return [
    "prices" => [
        7 => 2,
        30 => 569,
        90 => 1499,
        180 => 2899
    ],

    "withdrawTime" => [
        0 => 0,
        1 => 86400,   # через день
        2 => 86400,   # через 2 дня
        3 => 432000,  # через неделю
        4 => 2116800  # через месяц
    ],

    'dailyTokens' => [
        'free' => 10000,
        'trial' => 100000,
        'pro' => 300000
    ],

    'imageGenerations' => [
        'free' => 0,
        'trial' => 1,
        'pro' => 10
    ],

    "adminChat" => env('admin', '-4629052375'),
    "tg" => env('tg', "true") == 'true',
];
