<?php

declare(strict_types=1);

return [
    'sources' => [
        'chatgpt' => [
            'importer_key' => 'chatgpt',
            'access_modes' => [
                'archive',
                'local-path',
            ],
        ],
        'sms' => [
            'importer_key' => 'sms',
            'access_modes' => [
                'archive',
                'local-path',
            ],
        ],
        'facebook' => [
            'importer_key' => 'facebook',
            'access_modes' => [
                'local-path',
            ],
        ],
        'twitter' => [
            'importer_key' => 'twitter',
            'access_modes' => [
                'local-path',
            ],
        ],
        'linkedin' => [
            'importer_key' => 'linkedin',
            'access_modes' => [
                'local-path',
            ],
        ],
        'fidonet' => [
            'importer_key' => 'fidonet',
            'access_modes' => [
                'database',
            ],
        ],
        'gmail' => [
            'importer_key' => 'gmail',
            'access_modes' => [
                'api',
            ],
        ],
        'instagram' => [
            'importer_key' => 'instagram',
            'access_modes' => [
                'local-path',
            ],
        ],
        'media-collection' => [
            'importer_key' => 'media-collection',
            'access_modes' => [
                'db-connection',
            ],
        ],
    ],
];
