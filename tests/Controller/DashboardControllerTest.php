<?php

namespace Tests\Fregata\FregataBundle\Controller;

use Fregata\FregataBundle\Doctrine\Migration\MigrationEntity;

class DashboardControllerTest extends AbstractFunctionalTestCase
{
    /**
     * A default message is printed on a fresh installation
     */
    public function testDashboardDefaultMessage(): void
    {
        $this->client->request('GET', '/fregata/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('h1.title', 'No migration yet !');
        self::assertSelectorTextContains('div.notification', 'You haven\'t run any migration yet !');
    }

    /**
     * Running migrations are listed
     */
    public function testDashboardDisplayRunningMigrations(): void
    {
        $firstMigration = $this->createMigrationEntity();
        $secondMigration = $this->createMigrationEntity();
        $crawler = $this->client->request('GET', '/fregata/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('h1.title', 'Running migrations: 2');
        self::assertCount(2, $crawler->filter('table > tbody > tr'));
        self::assertSelectorTextSame(
            'table > tbody > tr:first-child > td:first-child',
            (string)$firstMigration->getId()
        );
        self::assertSelectorTextSame(
            'table > tbody > tr:last-child > td:first-child',
            (string)$secondMigration->getId()
        );
    }

    /**
     * The last run migration is shown when no running migration exists
     */
    public function testDashboardDisplayLastMigration(): void
    {
        $migration = $this->createMigrationEntity(MigrationEntity::STATUS_CANCELED);
        $crawler = $this->client->request('GET', '/fregata/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('h1.title', 'Last run migration:');
        self::assertCount(1, $crawler->filter('table > tbody > tr'));
        self::assertSelectorTextSame('table > tbody > tr > td:first-child', (string)$migration->getId());
    }
}
