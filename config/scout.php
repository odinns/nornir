<?php

declare(strict_types=1);
use App\Models\SearchDocument;

return [
    'driver' => env('SCOUT_DRIVER', 'meilisearch'),

    'prefix' => env('SCOUT_PREFIX', ''),

    'queue' => env('SCOUT_QUEUE', false),

    'after_commit' => false,

    'chunk' => [
        'searchable' => 500,
        'unsearchable' => 500,
    ],

    'soft_delete' => false,

    'identify' => env('SCOUT_IDENTIFY', false),

    'meilisearch' => [
        'host' => env('MEILISEARCH_HOST', 'http://127.0.0.1:7700'),
        'key' => env('MEILISEARCH_KEY', 'local-dev-key'),
        'index-settings' => [
            SearchDocument::class => [
                'filterableAttributes' => [
                    'source_type',
                    'source_table',
                    'occurred_at',
                    'participants',
                ],
                'sortableAttributes' => [
                    'occurred_at',
                ],
            ],
        ],
    ],
];
