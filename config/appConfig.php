<?php

declare(strict_types=1);

return [
    'mongo_uri' => getenv('MONGO_URI') ?: 'mongodb://mongo:27017',
    'mongo_db' => getenv('MONGO_DB') ?: 'viagem',
    'data_dir' => getenv('DATA_DIR') ?: dirname(__DIR__) . '/data/db',
    'dataset_url' => 'https://services.cuzk.gov.cz/gml/inspire/cpx/epsg-4258/%s.zip',
    'min_parcel_zoom' => 15,
    'bbox_limit' => 4000,
    'zonings' => [
        '659541' => 'Jičín',
        '641243' => 'Holín',
        '776530' => 'Valdice',
        '740225' => 'Robousy',
        '723746' => 'Podhradí u Jičína',
        '740217' => 'Moravčice',
        '724572' => 'Kbelnice u Jičína',
    ],
];
