<?php
// config/map_elements.php

return [
    'types' => ['route', 'marker', 'zone', 'infrastructure', 'group'],

    'subtypes' => [
        'route'          => ['course', 'pedestrian_route', 'vehicle_route', 'barrier', 'fence', 'annotation_line'],
        'marker'         => [
            'buoy', 'start', 'finish', 'checkpoint', 'aid_station',
            'medical', 'hazard', 'electricity', 'transition_marker',
            'timing_mat', 'spectator_area', 'bag_drop', 'feed_zone',
            // Entry & Access
            'entry_gate', 'exit_gate', 'ticket_check', 'wristband_collection', 'accreditation',
            // Workforce
            'steward', 'security', 'police', 'fire', 'volunteer', 'supervisor',
            // Annotations
            'text_label',
        ],
        'zone'           => [
            'restricted_area', 'parking_zone', 'transition_zone', 'start_zone',
            'finish_area', 'spectator_zone', 'media_zone', 'staging_area',
            'race_village', 'exclusion_zone',
            // Annotations
            'annotation_circle',
        ],
        'infrastructure' => [
            'tent', 'generator', 'toilet_block', 'stage', 'podium', 'timing_gantry',
            // Food & Beverage
            'food_stall', 'bar_drinks', 'water_point',
            // Branding & Signage
            'banner_arch', 'info_board',
            // Transport & Parking
            'bike_parking', 'shuttle_stop',
            // Annotations
            'annotation_rect',
        ],
        'group'          => [],
    ],
];
