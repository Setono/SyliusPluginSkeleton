<?php

declare(strict_types=1);

namespace Tests\Acme\SyliusExamplePlugin\DependencyInjection;

use Acme\SyliusExamplePlugin\DependencyInjection\Configuration;
use Matthias\SymfonyConfigTest\PhpUnit\ConfigurationTestCaseTrait;
use PHPUnit\Framework\TestCase;

/**
 * See examples of tests and configuration options here: https://github.com/SymfonyTest/SymfonyConfigTest
 */
final class ConfigurationTest extends TestCase
{
    use ConfigurationTestCaseTrait;

    protected function getConfiguration(): Configuration
    {
        return new Configuration();
    }

    /**
     * @test
     */
    public function values_are_invalid_if_required_value_is_not_provided(): void
    {
        $this->assertConfigurationIsInvalid(
            [
                [], // no values at all
            ],
            'The child node "option" at path "acme_sylius_example" must be configured.'
        );
    }

    /**
     * @test
     */
    public function processed_value_contains_required_value(): void
    {
        $this->assertProcessedConfigurationEquals([
            ['option' => 'first value'],
            ['option' => 'last value'],
        ], [
            'option' => 'last value',
        ]);
    }
}
