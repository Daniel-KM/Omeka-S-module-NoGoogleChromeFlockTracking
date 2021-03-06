<?php declare(strict_types=1);

namespace NoGoogleChromeFlockTracking;

return [
    'translator' => [
        'translation_file_patterns' => [
            [
                'type' => 'gettext',
                'base_dir' => dirname(__DIR__) . '/language',
                'pattern' => '%s.mo',
                'text_domain' => null,
            ],
        ],
    ],
    'nogooglechromeflocktracking' => [
    ],
];
