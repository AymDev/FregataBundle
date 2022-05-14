<?php

namespace Tests\Fregata\FregataBundle\Controller;

use Fregata\FregataBundle\Doctrine\Migration\MigrationEntity;
use Fregata\FregataBundle\Doctrine\Migration\MigrationRepository;

class RunControllerTest extends AbstractFunctionalTestCase
{
    /**
     * A new migration run can be started from the web interface
     */
    public function testStartNewRun(): void
    {
        $this->client->request('GET', '/fregata/run/new');
        self::assertResponseIsSuccessful();

        $this->client->submitForm('start_new_migration', [
            'start_migration_form[migration]' => 'test_migration',
        ]);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.notification.is-success', 'test_migration');
    }

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

    public function testCancelRun(): void
    {
        $migration = $this->createMigrationEntity(MigrationEntity::STATUS_MIGRATORS);
        $runUri = sprintf('/fregata/run/%d', $migration->getId());

        $this->client->request('GET', $runUri);
        self::assertResponseIsSuccessful();

        $this->client->clickLink('Cancel migration');
        self::assertResponseRedirects($runUri);

        $this->client->followRedirect();
        self::assertSelectorTextContains('.notification.is-success', 'Migration has been canceled.');
    }

    public function testCannotCancelRunWithInvalidToken(): void
    {
        $migration = $this->createMigrationEntity(MigrationEntity::STATUS_MIGRATORS);

        $this->client->request('GET', sprintf('/fregata/run/%d/cancel/invalid', $migration->getId()));
        self::assertResponseRedirects(sprintf('/fregata/run/%d', $migration->getId()));

        $this->client->followRedirect();
        self::assertSelectorTextContains('.notification.is-danger', 'Invalid security token.');
    }

    public function testCannotCancelRunForUnknownMigration(): void
    {
        $migration = $this->createMigrationEntity(MigrationEntity::STATUS_MIGRATORS);
        $this->client->request('GET', sprintf('/fregata/run/%d', $migration->getId()));
        self::assertResponseIsSuccessful();

        // Delete the migration
        $this->entityManager->remove($migration);
        $this->entityManager->flush();

        $this->client->clickLink('Cancel migration');
        self::assertResponseStatusCodeSame(404);
    }

    public function testCannotCancelRunForEndedMigration(): void
    {
        $migration = $this->createMigrationEntity(MigrationEntity::STATUS_MIGRATORS);
        $runUri = sprintf('/fregata/run/%d', $migration->getId());

        $this->client->request('GET', $runUri);
        self::assertResponseIsSuccessful();

        // End the migration
        $migration->setStatus(MigrationEntity::STATUS_FINISHED);
        $this->entityManager->flush();

        $this->client->clickLink('Cancel migration');
        self::assertResponseRedirects($runUri);

        $this->client->followRedirect();
        self::assertSelectorTextContains('.notification.is-warning', 'Cannot cancel migration as it already ended.');
    }
}
