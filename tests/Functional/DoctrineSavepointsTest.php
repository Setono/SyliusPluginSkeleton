<?php

declare(strict_types=1);

namespace Acme\SyliusExamplePlugin\Tests\Functional;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DoctrineSavepointsTest extends KernelTestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();

        restore_exception_handler();
    }

    #[Test]
    public function it_nests_transactions_with_savepoints(): void
    {
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');

        self::assertInstanceOf(Connection::class, $connection);
        self::assertTrue($connection->getNestTransactionsWithSavepoints());
    }
}
