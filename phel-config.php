<?php

declare(strict_types=1);

return [
    'src-dirs' => ['src/phel/'],
    'test-dirs' => ['tests/phel/'],
    'vendor-dir' => 'vendor',
    'export' => [
        'directories' => ['src/modules'],
        'namespace-prefix' => 'PhelGenerated',
        'target-directory' => 'src/PhelGenerated',
    ],
    'ignore-when-building' => [
        'test-keyboard.phel',
    ],
];
