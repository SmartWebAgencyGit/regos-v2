<?php

/*
|--------------------------------------------------------------------------
| Registration declarations
|--------------------------------------------------------------------------
|
| Wording ported verbatim from the legacy portal. ":link" is replaced by
| a link whose text comes from "links" (see config/regos.php "consents").
|
*/

return [

    'label' => [
        'safety_brief' => 'I have watched and understood the :link',
        'limited_car_parking' => 'Due to limited parking availability, we kindly ask all customers to carpool where possible.',
        'gamedate_may_change' => 'Game days may change. If your booking doesn’t go ahead, credits are issued. Bookings are non-refundable.',
        'accept_terms' => 'I have read and understand the :link',
        'agree_dispute_resolution' => 'I acknowledge that website imagery is illustrative and site-specific equipment/zones may vary. If dissatisfied, I agree to notify the Centre Manager on-site to allow for an "Opportunity to Cure." I agree that completing the event constitutes full acceptance of the service and will exhaust the company\'s formal complaint procedure before initiating any bank disputes or chargebacks.',

        'safety_equipment' => [
            'player' => 'I agree that the provided safety equipment available at the centre is there for my safety, and I agree that I will correctly wear all of the provided safety equipment including full length combat suit, body armour and full head paintball goggles. I will ensure all of my skin is covered whilst at the centre so as to minimise the chance of cuts, bruises, direct impact injuries, burns and other such injuries and/or irritations.',
            'dependent' => 'I agree that the provided safety equipment available at the centre is there for my dependent’s safety, and I agree that my dependent will correctly wear all of the provided safety equipment including full length combat suit, body armour and full head paintball goggles. I will ensure all of my dependent’s skin is covered whilst at the centre so as to minimise the chance of cuts, bruises, direct impact injuries, burns and other such injuries and/or irritations.',
        ],
        'agree_suitable_footwear' => [
            'player' => 'I agree that I will wear suitable footwear with deep tread and ankle support.',
            'dependent' => 'My dependent will wear suitable footwear with deep tread and ankle support.',
        ],
        'agree_proceed_caution' => [
            'player' => 'I agree that I will proceed with caution at all times whilst on site, including but not limited to not climbing/standing on structures, props, fences, picnic benches, trees or other items.',
            'dependent' => 'My dependent will proceed with caution at all times whilst on site, including but not limited to not climbing/standing on structures, props, fences, picnic benches, trees or other items.',
        ],
        'agree_supervision_dependent' => 'I agree that between paintball games whilst in the base camp and car park, myself or another responsible adult from my group will be required to provide full and complete supervision of my dependent.',
        'venues_spectator_friendly' => [
            'player' => 'Our paintball venues are not spectator friendly – I will not bring non-participating spectators to the event.',
            'dependent' => 'Our paintball venues are not spectator friendly – I will not bring non-participating spectators to the event other than myself and one additional responsible adult to help with supervision of my group between games.',
        ],
        'pyro_safety' => [
            'player' => 'Pyro Safety: The operator may supply pyrotechnic devices such as smoke grenades to enhance game play at the venue I am attending (depending on local bylaws). I confirm that I will comply with all displayed safe pyrotechnic use instructions and I will wear gloves, combat suit and eye protection when using pyrotechnics. I confirm that I will only use pyrotechnics if I am over 18 years of age and I will only use the manufacturer’s supplied ignition method to start a pyrotechnic. I will never use a cigarette lighter or any other alternate source of ignition to light a pyrotechnic. If a purchased pyrotechnic will not ignite, I will return it to the shop for a replacement. I will only use these pyrotechnics on site and during game play.',
            'dependent' => 'Pyro Safety: The operator may supply pyrotechnic devices such as smoke grenades to enhance game play at the venue I am attending (depending on local bylaws). I confirm that my dependent will not use or touch these devices as my dependent is under the age of 18 years old and is therefore prohibited from using them.',
            'uk' => 'Pyro Safety: The operator may supply pyrotechnic devices such as smoke grenades to enhance game play at the venue I am attending (depending on local bylaws). By ticking this box I confirm that I will comply with all displayed safe pyrotechnic use instructions and I will wear gloves, overalls and eye protection when using pyrotechnics.I confirm that I will only use pyrotechnics if I am over 18 years of age and I will only use the manufacturer’s supplied ignition method to start a pyrotechnic. I will never use a cigarette lighter or any other alternate source of ignition to light a pyrotechnic. If a purchased pyrotechnic will not ignite, I will return it to the shop for a replacement',
        ],
        'dispensing_equipment' => [
            'player' => 'I will take care when using any hot water dispensing equipment whilst on site. I assume full responsibility for handling any hot water in a safe manner.',
            'dependent' => 'My dependent will not use any hot water dispensing equipment whilst on site. If my dependent would like a hot beverage then I or another responsible adult from my group will take responsibility for this. Likewise I assume full responsibility for my dependent handling any hot water in a safe manner.',
        ],
        'parent_summary_sheet_check' => 'I hereby agree that I have read and understood the :link summary sheet (above) and I agree that my child will be supervised by an appropriate adult at all times whilst in the car park and base camp areas of the paintball centre.',
        'animal_safety_check' => 'Players must be driven to the Paintball car park inside the safari park, if any players are left at the main gate they will not be able to enter on foot. This is to protect you from the animals that are freely moving around the grounds. If you take public transport you will need to taxi or uber from the main gate to the paintball car park. Please find What 3 Words: ///trail.mute.since to help with locating the centre inside the grounds.',
        'early_arrival_parking_wait' => 'If you arrive at the centre early you may be asked to wait in the parking area until the team is ready to welcome you.',
        'slow_process_without_online_register' => 'If all players have not registered online in advance this will slow down the check in process.',
        'accept_promotions_sms_emails' => 'Please tick this box if you are happy for us to email and sms you with future promotions, offers and communication.',
        'guardian_permission' => 'I give permission for my dependent to participate in this paintball event',
    ],

    'links' => [
        'safety_video' => 'Paintball Safety Brief',
        'rules' => 'Indemnity Statement',
        'parent_info' => 'Information for parents',
    ],

    'errors' => [
        'default' => 'Please accept the Terms and Conditions',
        'safety_brief' => 'Please watch the safety brief',
        'guardian_permission' => 'The Guardian must provide permission',
    ],

];
