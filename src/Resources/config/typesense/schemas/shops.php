<?php

return [
    'name' => 'shops',
    'fields' => [
        [
            'name' => 'name',
            'type' => 'string'
        ],
        [
            'name' => 'type',
            'type' => 'string',
            'facet' => true
        ],
        [
            'name' => 'cuisine',
            'type' => 'string[]',
            'facet' => true
        ],
        [
            'name' => 'category',
            'type' => 'string[]',
            'facet' => true
        ],
        [
            'name' => 'enabled',
            'type' => 'bool',
        ],
        [
            'name' => '.*_facet',
            'type' => 'auto',
            'facet' => true
        ],
    ]
];
