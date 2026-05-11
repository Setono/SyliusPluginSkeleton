<?php

declare(strict_types=1);

namespace Acme\SyliusExamplePlugin\Tests\Unit\DependencyInjection;

use Acme\SyliusExamplePlugin\DependencyInjection\AcmeSyliusExampleExtension;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractExtensionTestCase;
use PHPUnit\Framework\Attributes\Test;

final class AcmeSyliusExampleExtensionTest extends AbstractExtensionTestCase
{
    protected function getContainerExtensions(): array
    {
        return [
            new AcmeSyliusExampleExtension(),
        ];
    }

    #[Test]
    public function it_loads(): void
    {
        $this->expectNotToPerformAssertions();

        $this->load();
    }
}
