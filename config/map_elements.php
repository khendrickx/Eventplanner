<?php
// config/map_elements.php

return [
    'types' => ['route', 'marker', 'zone', 'infrastructure'],

    'subtypes' => [
        'route' => ['course', 'pedestrian_route', 'vehicle_route', 'barrier', 'fence'],
        'marker' => [
            'buoy', 'start', 'finish', 'checkpoint', 'aid_station',
            'medical', 'hazard', 'electricity', 'transition_zone',
            'timing_mat', 'spectator_area', 'bag_drop', 'feed_zone',
        ],
        'zone' => [
            'restricted_area', 'parking_zone', 'transition_zone', 'start_zone',
            'finish_area', 'spectator_zone', 'media_zone', 'staging_area',
            'race_village', 'exclusion_zone',
        ],
        'infrastructure' => [
            'tent', 'generator', 'toilet_block', 'stage', 'podium', 'timing_gantry',
        ],
    ],
];
