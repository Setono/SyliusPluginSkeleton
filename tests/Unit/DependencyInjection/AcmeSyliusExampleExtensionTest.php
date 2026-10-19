<?php

declare(strict_types=1);

namespace Acme\SyliusExamplePlugin\Tests\Unit\DependencyInjection;

use Acme\SyliusExamplePlugin\DependencyInjection\AcmeSyliusExampleExtension;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractExtensionTestCase;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Config\Resource\ResourceInterface;

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

    #[Test]
    public function after_loading_the_service_definitions_have_been_loaded(): void
    {
        $this->load();

        $resources = array_map(static fn (ResourceInterface $resource): string => (string) $resource, $this->container->getResources());

        self::assertContains(dirname(__DIR__, 3) . '/config/services.php', $resources);
    }
}
