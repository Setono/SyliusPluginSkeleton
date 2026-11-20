<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Composer\InstalledVersions;

/*
 * Nested transactions need savepoints. dama/doctrine-test-bundle, for one, nests each test's transactions in its own,
 * and its container doesn't compile without them. DBAL 3, which Sylius 2.2 requires, makes savepoints opt-in through
 * use_savepoints. DBAL 4, which Sylius 2.3 allows, always uses them: DoctrineBundle 2 deprecates the key there, and
 * DoctrineBundle 3 removed it, so the container fails with 'Unrecognized option "use_savepoints"'.
 * Delete this file once Sylius requires DBAL 4.
 */
return static function (ContainerConfigurator $container): void {
    if (version_compare((string) InstalledVersions::getVersion('doctrine/dbal'), '4', '>=')) {
        return;
    }

    $container->extension('doctrine', [
        'dbal' => [
            'use_savepoints' => true,
        ],
    ]);
};
