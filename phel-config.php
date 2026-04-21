<?php

declare(strict_types=1);

use Phel\Config\PhelBuildConfig;
use Phel\Config\PhelConfig;

return (new PhelConfig())
    ->useNestedLayout()
    ->setBuildConfig((new PhelBuildConfig())
        ->setMainPhpPath('out/main.php')
        ->setMainPhelNamespace('phel-cli-gui\terminal-gui'));
