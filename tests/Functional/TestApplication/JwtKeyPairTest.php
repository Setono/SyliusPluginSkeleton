<?php

declare(strict_types=1);

namespace Acme\SyliusExamplePlugin\Tests\Functional\TestApplication;

use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The private key is only read when a token is signed, so a JWT_PASSPHRASE that doesn't
 * decrypt it goes unnoticed until the first API test logs in.
 */
final class JwtKeyPairTest extends KernelTestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();

        restore_exception_handler();
    }

    #[Test]
    public function it_signs_and_verifies_tokens_with_the_shipped_key_pair(): void
    {
        $encoder = self::getContainer()->get('lexik_jwt_authentication.encoder');
        self::assertInstanceOf(JWTEncoderInterface::class, $encoder);

        $payload = $encoder->decode($encoder->encode(['username' => 'api@example.com']));

        self::assertSame('api@example.com', $payload['username']);
    }
}
