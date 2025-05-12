<?php

declare(strict_types=1);

namespace Acme\SyliusExamplePlugin\Tests;

use PHPUnit\Framework\TestCase;

/**
 * This dummy is only made to allow PHPUnit and Infection to run tests
 */
final class DummyTest extends TestCase
{
    /**
     * @test
     */
    public function it_does_nothing(): void
    {
        $this->assertTrue(true);
    }
}
