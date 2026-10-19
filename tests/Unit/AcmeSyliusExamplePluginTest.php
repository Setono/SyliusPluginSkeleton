<?php

declare(strict_types=1);

namespace Acme\SyliusExamplePlugin\Tests\Unit;

use Acme\SyliusExamplePlugin\AcmeSyliusExamplePlugin;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AcmeSyliusExamplePluginTest extends TestCase
{
    #[Test]
    public function its_path_is_the_repository_root(): void
    {
        self::assertSame(dirname(__DIR__, 2), (new AcmeSyliusExamplePlugin())->getPath());
    }
}
