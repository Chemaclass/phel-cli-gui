<?php

declare(strict_types=1);

use Phel\Config\PhelBuildConfig;
use Phel\Config\PhelConfig;

return (new PhelConfig())
    ->setSrcDirs(['src/phel/'])
    ->setTestDirs(['tests/phel/'])
    ->setVendorDir('vendor')
    ->setFormatDirs(['src', 'tests'])
    ->setBuildConfig((new PhelBuildConfig())
        ->setMainPhpPath('out/main.php')
        ->setMainPhelNamespace('phel-cli-gui\terminal-gui'))
    ->setIgnoreWhenBuilding(['local.phel', 'test-keyboard.phel'])
    ->setKeepGeneratedTempFiles(false)
;
