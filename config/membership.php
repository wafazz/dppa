<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Membership Settings
    |--------------------------------------------------------------------------
    |
    | consent_version is stored with every application so the consent wording
    | a member agreed to can be traced later. Change it whenever the consent
    | text in the registration form changes.
    |
    | member_no_prefix is used to build member IDs such as DPPA-2026-0001.
    |
    */

    'consent_version' => '2026-11-v1',

    'member_no_prefix' => 'DPPA',

    /*
    | States and federal territories offered on profile addresses.
    */
    'states' => [
        'Johor',
        'Kedah',
        'Kelantan',
        'Melaka',
        'Negeri Sembilan',
        'Pahang',
        'Pulau Pinang',
        'Perak',
        'Perlis',
        'Sabah',
        'Sarawak',
        'Selangor',
        'Terengganu',
        'Wilayah Persekutuan Kuala Lumpur',
        'Wilayah Persekutuan Labuan',
        'Wilayah Persekutuan Putrajaya',
    ],

];
