<?php

declare(strict_types=1);

use Symplify\EasyCodingStandard\Config\ECSConfig;

return static function (ECSConfig $config): void {
    $config->import('vendor/sylius-labs/coding-standard/ecs.php');
    $config->paths([
        'src',
        'tests',
        'composer-dependency-analyser.php',
        'rector.php',
    ]);
    $config->skip([
        // Generated (and gitignored): Symfony 7.4 writes it whenever it builds the debug container
        'tests/Application/config/reference.php',
        'tests/Application/node_modules/**',
        'tests/Application/var/**',
    ]);
};
