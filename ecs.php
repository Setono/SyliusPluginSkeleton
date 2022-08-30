<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symplify\EasyCodingStandard\ValueObject\Option;

return static function (ContainerConfigurator $configurator): void {
    $configurator->import('vendor/sylius-labs/coding-standard/ecs.php');
    $configurator->parameters()->set(Option::PATHS, [
        'src',
        'tests',
    ]);
    $configurator->parameters()->set(Option::SKIP, [
        'tests/Application/node_modules/**',
        'tests/Application/var/**',
    ]);
};
