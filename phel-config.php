<?php

declare(strict_types=1);

use Phel\Config\PhelConfig;
use Phel\Config\PhelExportConfig;

return (new PhelConfig())
    ->setSrcDirs(['src/phel/'])
    ->setTestDirs(['tests/phel/'])
    ->setVendorDir('vendor')
    ->setOutDir('out')
    ->setExport(
        (new PhelExportConfig())
            ->setDirectories(['src/modules'])
            ->setNamespacePrefix('PhelGenerated')
            ->setTargetDirectory('src/PhelGenerated')
    )
    ->setIgnoreWhenBuilding(['local.phel'])
    ->setKeepGeneratedTempFiles(false)
    ->setIgnoreWhenBuilding(['test-keyboard.phel']);
