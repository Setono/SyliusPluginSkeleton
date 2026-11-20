<?php

declare(strict_types=1);

namespace Acme\SyliusExamplePlugin\Tests\Functional;

use PHPUnit\Framework\Attributes\Test;
use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\User\Repository\UserRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

final class AdminAssetsTest extends WebTestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();

        restore_exception_handler();
    }

    #[Test]
    public function it_loads_the_plugin_admin_entry_on_admin_pages(): void
    {
        $crawler = $this->requestAdminDashboard();

        self::assertCount(1, $crawler->filter('script[src^="/build/app/admin/plugin-admin-entry."]'));
    }

    /**
     * The test application builds Sylius' admin without a Stimulus app, so without this entry Sylius' own controllers
     * (autocomplete, live components, slugs) never connect, and the live components' styles are missing
     */
    #[Test]
    public function it_loads_the_stimulus_app_and_its_styles_on_admin_pages(): void
    {
        $crawler = $this->requestAdminDashboard();

        self::assertCount(1, $crawler->filter('script[src^="/build/app/admin/app-admin-entry."]'));
        self::assertCount(1, $crawler->filter('link[rel="stylesheet"][href^="/build/app/admin/app-admin-entry."]'));
    }

    private function requestAdminDashboard(): Crawler
    {
        $client = self::createClient();
        $this->logInAsAdministrator($client);

        $crawler = $client->request('GET', '/admin/');
        self::assertResponseIsSuccessful();

        return $crawler;
    }

    private function logInAsAdministrator(KernelBrowser $client): void
    {
        $repository = self::getContainer()->get('sylius.repository.admin_user');
        self::assertInstanceOf(UserRepositoryInterface::class, $repository);

        // The administrator from the default fixtures
        $administrator = $repository->findOneByEmail('sylius@example.com');
        self::assertInstanceOf(AdminUserInterface::class, $administrator);

        $client->loginUser($administrator, 'admin');
    }
}
