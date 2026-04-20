<?php

declare(strict_types=1);

namespace Acme\SyliusExamplePlugin\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    /** @phpstan-ignore missingType.generics (TreeBuilder is generic in Symfony >=7.1 but not in 6.4) */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        return new TreeBuilder('acme_sylius_example');
    }
}
