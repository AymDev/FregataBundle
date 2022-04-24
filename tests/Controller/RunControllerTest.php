<?php

namespace Tests\Fregata\FregataBundle\Controller;

use Fregata\FregataBundle\Doctrine\Migration\MigrationEntity;
use Fregata\FregataBundle\Doctrine\Migration\MigrationRepository;

class RunControllerTest extends AbstractFunctionalTestCase
{
    /**
     * The run history lists all runs
     */
    public function testRunHistory(): void
    {
        $this->createMigrationEntity();
        $this->createMigrationEntity(MigrationEntity::STATUS_FINISHED, 'other_migration');

        $crawler = $this->client->request('GET', '/fregata/run/history');

        self::assertResponseIsSuccessful();
        self::assertCount(2, $crawler->filter('table > tbody > tr'));
    }

    /**
     * The run history pagination works
     */
    public function testRunHistoryPagination(): void
    {
        // Migrations
        $oldest = $this->createMigrationEntity();

        for ($i = 1; $i < MigrationRepository::PAGINATION_OFFSET; $i++) {
            $this->createMigrationEntity();
        }

        $newest = $this->createMigrationEntity();

        // First page (default)
        $this->client->request('GET', '/fregata/run/history');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('table > tbody > tr > td', $newest->getId());

        // Second page
        $this->client->request('GET', '/fregata/run/history', [
            'page' => 2
        ]);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('table > tbody > tr > td', $oldest->getId());
    }

    public function testRunDetails(): void
    {
        // Unknown migration run
        $this->client->request('GET', '/fregata/run/0');
        self::assertResponseStatusCodeSame(404);

        // Existing migration
        $migration = $this->createMigrationEntity(MigrationEntity::STATUS_MIGRATORS);
        $this->client->request('GET', sprintf('/fregata/run/%d', $migration->getId()));
        self::assertResponseIsSuccessful();
    }
}
