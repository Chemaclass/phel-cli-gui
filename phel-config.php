<?php

declare(strict_types=1);

use Phel\Config\PhelBuildConfig;
use Phel\Config\PhelConfig;
use Phel\Config\ProjectLayout;

return (new PhelConfig())
    ->withLayout(ProjectLayout::Nested)
    ->withBuildConfig((new PhelBuildConfig())
        ->withMainPhpPath('out/main.php')
        ->withMainPhelNamespace('phel-cli-gui\terminal-gui'));
