<?php

/*
|--------------------------------------------------------------------------
| Player Portal ("regos") configuration
|--------------------------------------------------------------------------
|
| Everything that was previously hard-coded across the legacy Mithril
| bundle and the PHP API (links, country rules, consent rules, payment
| settings, network ranges) lives here so it can be changed in one place.
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | HAPI (Xena booking API)
    |--------------------------------------------------------------------------
    |
    | The portal has no database of its own: bookings, customers and
    | registrations all live in HAPI. Set "fake" to true for local
    | development to use built-in demo data instead of the real API.
    |
    */

    'hapi' => [
        'url' => env('HAPI_URL', 'http://129.232.225.76/api'),
        'username' => env('HAPI_USERNAME'),
        'password' => env('HAPI_PASSWORD'),
        'system' => env('HAPI_SYSTEM', 'alpha'),
        'token_ttl' => (int) env('HAPI_TOKEN_TTL', 1800),
        'timeout' => (int) env('HAPI_TIMEOUT', 20),
        // Saves, which can be slow. Keep below the web server's own limit (nginx: 60s).
        'write_timeout' => (int) env('HAPI_WRITE_TIMEOUT', 50),
        'fake' => (bool) env('HAPI_FAKE', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | On-site networks
    |--------------------------------------------------------------------------
    |
    | Requests from these ranges (arriving over the site VPNs) are treated
    | as "internal": the visitor can pick their group from today's bookings
    | on a shared device. Maps CIDR range => HAPI site id.
    |
    */

    'internal_networks' => [
        '10.3.25.0/24' => 55,  // Newcastle
        '10.3.2.0/24' => 3,    // Appin
        '10.3.10.0/24' => 50,  // Petrie
        '10.1.2.0/24' => 185,  // Gerrards Cross
        '10.1.3.0/24' => 87,   // Cobham
        '10.1.4.0/24' => 93,   // Upminster A
        '10.1.5.0/24' => 207,  // Upminster B
        '10.1.6.0/24' => 88,   // Hemel Hempstead
    ],

    /*
    | Proxies allowed to set X-Forwarded-For. Leave empty when PHP sees the
    | client address directly (the legacy app trusted the header from anyone,
    | which allowed spoofing an on-site address).
    */

    'trusted_proxies' => env('TRUSTED_PROXIES'),

    /*
    |--------------------------------------------------------------------------
    | Branding
    |--------------------------------------------------------------------------
    */

    'brand' => [
        'name' => 'Delta Force Paintball',
        'phone' => env('REGOS_PHONE', '1300 361 767'),
        'website' => env('REGOS_WEBSITE', 'http://www.deltaforcepaintball.com.au'),
        'default_safety_video' => 'ZlTZKLFly10',
    ],

    /*
    |--------------------------------------------------------------------------
    | Countries
    |--------------------------------------------------------------------------
    |
    | Keyed by HAPI country id. "rules" selects the indemnity wording shown
    | on /rules ("au" = playing rules / risk / release, "uk" = UK terms).
    | "itinerary" is keyed by gametype id, with "default" as the fallback
    | (gametype 2 is MiniBall). Each country's Worldpay merchant code can be
    | changed in .env; it logs in with WORLDPAY_PASSWORD unless the country
    | has its own password set.
    |
    */

    'countries' => [
        1 => [
            'code' => 'GB',
            'rules' => 'uk',
            'safety_video' => 'UBeM9aJyBTI',
            'parent_info' => 'https://www.paintballbookingoffice.com/info/Parental_Information_UK.pdf',
            'privacy_policy' => 'https://www.paintballgames.co.uk/privacy-statement/',
            'info_pack' => 'https://www.paintballgames.co.uk/information-packs/',
            'itinerary' => [
                'default' => 'https://www.paintballgames.co.uk/the-paintball-experience/day-itinerary/',
                2 => 'https://www.paintballgames.co.uk/the-paintball-experience/miniball-day-itinerary/',
            ],
            'handling_fee_rate' => 0.0699,
            'tax_rate' => 0,
            'merchant_code' => env('WORLDPAY_MERCHANT_GB', 'WORLDPAINTBALLUKCCGBP'),
            'merchant_password' => env('WORLDPAY_PASSWORD_GB'),
        ],
        2 => [
            'code' => 'IE',
            'rules' => 'uk',
            'handling_fee_rate' => 0.0699,
            'tax_rate' => 0,
            'merchant_code' => env('WORLDPAY_MERCHANT_IE', 'WORLDPAINTBALLIECCGBP'),
            'merchant_password' => env('WORLDPAY_PASSWORD_IE'),
        ],
        3 => [
            'code' => 'AU',
            'rules' => 'au',
            'safety_video' => 'm-NG_8EwbMs',
            'parent_info' => 'https://www.paintballbookingoffice.com/info/Parental_Information_AU.pdf',
            'info_pack' => 'https://www.deltaforcepaintball.com.au/info/info',
            'itinerary' => [
                'default' => 'https://www.paintballbookingoffice.com/info/DF-AUNZ-Winter-Paintball-Itinerary.pdf',
                2 => 'https://www.paintballbookingoffice.com/info/PBBO-Miniball-Itinerary-Summer-AU-NZ-From-June-1st-1.pdf',
            ],
            'handling_fee_rate' => 0.0599,
            'tax_rate' => 0,
            'merchant_code' => env('WORLDPAY_MERCHANT_AU', 'WORLDPAINTBALLAUCCGBP'),
            'merchant_password' => env('WORLDPAY_PASSWORD_AU'),
        ],
        4 => [
            'code' => 'NZ',
            'rules' => 'au',
            'safety_video' => 'zNg73xBv8Jc',
            'parent_info' => 'https://www.paintballbookingoffice.com/info/Parental_Information_NZ.pdf',
            'info_pack' => 'https://www.paintballing.co.nz/information-packs',
            'itinerary' => [
                'default' => 'https://www.paintballbookingoffice.com/info/DF-AUNZ-Winter-Paintball-Itinerary.pdf',
                2 => 'https://www.paintballing.co.nz/wp-content/uploads/DF-AUNZ-Winter-Miniball-Itinerary.pdf',
            ],
            'handling_fee_rate' => 0.0599,
            'tax_rate' => 0,
            'merchant_code' => env('WORLDPAY_MERCHANT_NZ', 'WORLDPAINTBALLNZCCGBP'),
            'merchant_password' => env('WORLDPAY_PASSWORD_NZ'),
        ],
        5 => [
            'code' => 'CA',
            'rules' => 'au',
            'safety_video' => 'jFCMCIkYFDY',
            'parent_info' => 'https://www.paintballbookingoffice.com/info/Parental_Information_CA.pdf',
            'info_pack' => 'https://www.canadapaintballing.ca/information-packs/',
            'itinerary' => [
                'default' => 'https://www.canadapaintballing.ca/CA_Itinerary.pdf',
            ],
            'handling_fee_rate' => 0.0699,
            'tax_rate' => 0.13,
            'merchant_code' => env('WORLDPAY_MERCHANT_CA', 'WORLDPAINTBALLCACCGBP'),
            'merchant_password' => env('WORLDPAY_PASSWORD_CA'),
        ],
        6 => [
            'code' => 'SE',
            'rules' => 'uk',
            'handling_fee_rate' => 0,
            'tax_rate' => 0,
            'merchant_code' => env('WORLDPAY_MERCHANT_SE'),
            'merchant_password' => env('WORLDPAY_PASSWORD_SE'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Per-site overrides
    |--------------------------------------------------------------------------
    |
    | Keyed by country id, then by the HAPI site name (names repeat across
    | countries, e.g. "Newcastle" exists in the UK and Australia).
    |
    */

    'sites' => [
        1 => [
            'Banbury' => ['map' => '/pdfs/maps/uk/Map United Kingdom Banbury.pdf'],
            'Birmingham' => ['map' => '/pdfs/maps/uk/Map United Kingdom Birmingham.pdf'],
            'Cardiff' => ['map' => '/pdfs/maps/uk/Map United Kingdom Cardiff.pdf'],
            'Cobham' => ['map' => '/pdfs/maps/uk/Map United Kingdom Cobham.pdf'],
            'Hemel Hempstead' => ['map' => '/pdfs/maps/uk/Map United Kingdom Hemel Hempstead.pdf'],
            'Kegworth' => ['map' => '/pdfs/maps/uk/Map United Kingdom Kegworth.pdf'],
            'Liverpool' => ['map' => '/pdfs/maps/uk/Map United Kingdom Liverpool.pdf'],
            'Maidenhead' => ['map' => '/pdfs/maps/uk/Map United Kingdom Maidenhead.pdf'],
            'Manchester' => ['map' => '/pdfs/maps/uk/Map United Kingdom Manchester.pdf'],
            'Newcastle' => ['map' => '/pdfs/maps/uk/Map United Kingdom Newcastle.pdf'],
            'North Bristol' => ['map' => '/pdfs/maps/uk/Map United Kingdom North Bristol.pdf'],
            'Orpington' => ['map' => '/pdfs/maps/uk/Map United Kingdom Orpington.pdf'],
            'Plymouth' => ['map' => '/pdfs/maps/uk/Map United Kingdom Plymouth.pdf'],
            'Southampton' => ['map' => '/pdfs/maps/uk/Map United Kingdom Southampton.pdf'],
            'Upminster Alpha' => ['map' => '/pdfs/maps/uk/Map United Kingdom Upminster Alpha.pdf'],
            'Wakefield' => ['map' => '/pdfs/maps/uk/Map United Kingdom Wakefield.pdf'],
            'West Glasgow' => ['map' => '/pdfs/maps/uk/Map United Kingdom West Glasgow.pdf'],
        ],
        3 => [
            'Appin' => ['info_pack' => 'https://www.paintballing.com.au/delta-force-paintball-appin-information-packs'],
            'Helensburgh' => ['info_pack' => 'https://www.paintballing.com.au/delta-force-paintball-appin-information-packs'],
            'Bonneys' => ['info_pack' => 'https://www.paintballing.com.au/delta-force-paintball-bonneys-information-packs'],
            'Canberra' => ['info_pack' => 'https://www.paintballing.com.au/delta-force-paintball-canberra-information-packs'],
            'Monarto' => ['info_pack' => 'https://www.paintballing.com.au/delta-force-paintball-monarto-information-packs'],
            'Newcastle' => ['info_pack' => 'https://www.paintballing.com.au/delta-force-paintball-newcastle-information-packs'],
            'Petrie' => ['info_pack' => 'https://www.paintballing.com.au/delta-force-paintball-petrie-information-packs'],
            'Dingley' => ['info_pack' => 'https://www.paintballing.com.au/delta-force-paintball-melbourne-information-packs-2'],
        ],
    ],

    /*
    | Sites (by name, any country) where the event links (info pack,
    | directions, itinerary) and "Add Players" are hidden.
    */

    'restricted_sites' => ['Leicester'],

    /*
    |--------------------------------------------------------------------------
    | Registration
    |--------------------------------------------------------------------------
    */

    'registration' => [
        'guardian_age' => 16,          // players younger than this need a guardian
        'dependent_age' => 13,         // younger than this get the "dependent" declarations
        'youngest_player_age' => 6,    // date-of-birth picker upper bound
        'interaction_tag' => 2,
    ],

    /*
    |--------------------------------------------------------------------------
    | Consent declarations
    |--------------------------------------------------------------------------
    |
    | Drives both the registration form and its validation, in display order.
    | Labels live in lang/en/consents.php under "label". Optional filters:
    |   countries / except_countries  - HAPI country ids
    |   sites                         - HAPI site names
    |   min_age / max_age             - inclusive, in whole years
    |   link                          - safety_video | rules | parent_info
    |   required                      - defaults to true
    |
    */

    'consents' => [
        ['field' => 'safety_brief', 'label' => 'safety_brief', 'link' => 'safety_video'],
        ['field' => 'limited_car_parking', 'label' => 'limited_car_parking'],
        ['field' => 'gamedate_may_change', 'label' => 'gamedate_may_change'],
        ['field' => 'accept_terms', 'label' => 'accept_terms', 'link' => 'rules'],
        ['field' => 'agree_dispute_resolution', 'label' => 'agree_dispute_resolution'],

        ['field' => 'safety_equipment', 'label' => 'safety_equipment.player', 'except_countries' => [1], 'min_age' => 13],
        ['field' => 'agree_suitable_footwear', 'label' => 'agree_suitable_footwear.player', 'except_countries' => [1], 'min_age' => 13],
        ['field' => 'agree_proceed_caution', 'label' => 'agree_proceed_caution.player', 'except_countries' => [1], 'min_age' => 13],
        ['field' => 'venues_spectator_friendly', 'label' => 'venues_spectator_friendly.player', 'except_countries' => [1], 'min_age' => 13],
        ['field' => 'pyro_safety', 'label' => 'pyro_safety.player', 'except_countries' => [1], 'min_age' => 13],
        ['field' => 'dispensing_equipment', 'label' => 'dispensing_equipment.player', 'except_countries' => [1], 'min_age' => 13],

        ['field' => 'safety_equipment', 'label' => 'safety_equipment.dependent', 'except_countries' => [1], 'max_age' => 12],
        ['field' => 'agree_suitable_footwear', 'label' => 'agree_suitable_footwear.dependent', 'except_countries' => [1], 'max_age' => 12],
        ['field' => 'agree_proceed_caution', 'label' => 'agree_proceed_caution.dependent', 'except_countries' => [1], 'max_age' => 12],
        ['field' => 'agree_supervision_dependent', 'label' => 'agree_supervision_dependent', 'except_countries' => [1], 'max_age' => 12],
        ['field' => 'venues_spectator_friendly', 'label' => 'venues_spectator_friendly.dependent', 'except_countries' => [1], 'max_age' => 12],
        ['field' => 'pyro_safety', 'label' => 'pyro_safety.dependent', 'except_countries' => [1], 'max_age' => 12],
        ['field' => 'dispensing_equipment', 'label' => 'dispensing_equipment.dependent', 'except_countries' => [1], 'max_age' => 12],

        ['field' => 'parent_summary_sheet_check', 'label' => 'parent_summary_sheet_check', 'max_age' => 12, 'link' => 'parent_info'],

        ['field' => 'pyro_safety', 'label' => 'pyro_safety.uk', 'countries' => [1]],
        ['field' => 'animal_safety_check', 'label' => 'animal_safety_check', 'sites' => ['Liverpool']],
        ['field' => 'early_arrival_parking_wait', 'label' => 'early_arrival_parking_wait', 'countries' => [1]],
        ['field' => 'slow_process_without_online_register', 'label' => 'slow_process_without_online_register', 'countries' => [1]],
        ['field' => 'accept_promotions_sms_emails', 'label' => 'accept_promotions_sms_emails', 'countries' => [1], 'required' => false],
    ],

    /*
    |--------------------------------------------------------------------------
    | Add extra players (paid)
    |--------------------------------------------------------------------------
    */

    'add_players' => [
        'hidden_countries' => [5],
        'default_max_quantity' => 100,
        'package_limits' => [   // package_id => max extra players per order
            7175277 => 10,      // Wowcher 100+50
            8611699 => 15,      // GRPN Horizon Teens
            8611706 => 15,      // GRPN PB Networks Adults
        ],
        'interaction_tag' => 7173821,
        'email_template' => 7170079,
        'notify' => [
            'address' => env('REGOS_BOOKING_OFFICE_EMAIL', 'email@paintballbookingoffice.com'),
            'name' => 'Paintball Bookingoffice',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Payments
    |--------------------------------------------------------------------------
    |
    | "worldpay" charges cards through the Worldpay XML Direct API. "fake"
    | approves every card except 4000 0000 0000 0002 and is refused outside
    | local/testing environments.
    |
    */

    'payments' => [
        'driver' => env('REGOS_PAYMENT_DRIVER', 'worldpay'),
        'worldpay' => [
            'url' => env('WORLDPAY_URL', 'https://secure.worldpay.com/jsp/merchant/xml/paymentService.jsp'),
            'password' => env('WORLDPAY_PASSWORD'),
            'timeout' => (int) env('WORLDPAY_TIMEOUT', 60),
        ],
    ],

];
