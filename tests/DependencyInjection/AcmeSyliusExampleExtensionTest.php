<?php

declare(strict_types=1);

namespace Acme\SyliusExamplePlugin\Tests\DependencyInjection;

use Acme\SyliusExamplePlugin\DependencyInjection\AcmeSyliusExampleExtension;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractExtensionTestCase;

final class AcmeSyliusExampleExtensionTest extends AbstractExtensionTestCase
{
    protected function getContainerExtensions(): array
    {
        return [
            new AcmeSyliusExampleExtension(),
        ];
    }

    /**
     * @test
     */
    public function it_loads(): void
    {
        $this->load();

        self::assertTrue(true);
    }
}
