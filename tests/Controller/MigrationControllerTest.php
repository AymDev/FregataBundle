<?php

namespace Tests\Fregata\FregataBundle\Controller;

use Fregata\FregataBundle\Doctrine\Migration\MigrationEntity;
use Fregata\FregataBundle\Doctrine\Migration\MigrationRepository;

class MigrationControllerTest extends AbstractFunctionalTestCase
{
    /**
     * Configured migrations are listed
     */
    public function testMigrationList(): void
    {
        $crawler = $this->client->request('GET', '/fregata/migration');

        self::assertResponseIsSuccessful();
        self::assertCount(2, $crawler->filter('table > tbody > tr'));
        self::assertSelectorTextSame('table > tbody > tr > td', 'test_migration');
    }

    /**
     * Unknown migrations with no run history return a 404 response
     */
    public function testUnknownMigration(): void
    {
        $this->client->request('GET', '/fregata/migration/unknown');

        self::assertResponseStatusCodeSame(404);
    }

    /**
     * Unknown migrations with run history are available
     */
    public function testArchivedMigration(): void
    {
        $migration = $this->createMigrationEntity(MigrationEntity::STATUS_FINISHED, 'archived');
        $this->client->request('GET', '/fregata/migration/archived');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.message', 'Archived migration');
        self::assertSelectorTextSame('table > tbody > tr > td', $migration->getId());
    }

    /**
     * Configured migrations are available
     */
    public function testConfiguredMigration(): void
    {
        // No run history
        $this->client->request('GET', '/fregata/migration/test_migration');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.panel', 'Components');

        // Run history
        $migration = $this->createMigrationEntity();
        $this->client->request('GET', '/fregata/migration/test_migration');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('table > tbody > tr > td', $migration->getId());
    }

    /**
     * Pagination works
     */
    public function testPagination(): void
    {
        // Migrations
        $oldest = $this->createMigrationEntity();

        for ($i = 1; $i < MigrationRepository::PAGINATION_OFFSET; $i++) {
            $this->createMigrationEntity();
        }

        $newest = $this->createMigrationEntity();

        // First page (default)
        $this->client->request('GET', '/fregata/migration/test_migration');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('table > tbody > tr > td:first-child', $newest->getId());

        // Second page
        $this->client->request('GET', '/fregata/migration/test_migration', [
            'page' => 2
        ]);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('table > tbody > tr > td:first-child', $oldest->getId());
    }
}
