<?php

declare(strict_types=1);

use Phel\Config\PhelConfig;
use Phel\Config\PhelExportConfig;
use Phel\Config\PhelOutConfig;

return (new PhelConfig())
    ->setSrcDirs(['src/phel/'])
    ->setTestDirs(['tests/phel/'])
    ->setVendorDir('vendor')
    ->setOut((new PhelOutConfig())
        ->setDestDir('out'))
    ->setExport(
        (new PhelExportConfig())
            ->setDirectories(['src/modules'])
            ->setNamespacePrefix('PhelGenerated')
            ->setTargetDirectory('src/PhelGenerated')
    )
    ->setIgnoreWhenBuilding(['local.phel'])
    ->setKeepGeneratedTempFiles(false)
    ->setIgnoreWhenBuilding(['test-keyboard.phel']);
