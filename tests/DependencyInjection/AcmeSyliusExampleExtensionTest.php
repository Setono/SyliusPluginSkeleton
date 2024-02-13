<?php

declare(strict_types=1);

namespace Acme\SyliusExamplePlugin\Tests\DependencyInjection;

use Acme\SyliusExamplePlugin\DependencyInjection\AcmeSyliusExampleExtension;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractExtensionTestCase;

/**
 * See examples of tests and configuration options here: https://github.com/SymfonyTest/SymfonyDependencyInjectionTest
 */
final class AcmeSyliusExampleExtensionTest extends AbstractExtensionTestCase
{
    protected function getContainerExtensions(): array
    {
        return [
            new AcmeSyliusExampleExtension(),
        ];
    }

    protected function getMinimalConfiguration(): array
    {
        return [
            'option' => 'option_value',
        ];
    }

    /**
     * @test
     */
    public function after_loading_the_correct_parameter_has_been_set(): void
    {
        $this->load();

        $this->assertContainerBuilderHasParameter('acme_sylius_example.option', 'option_value');
    }
}
