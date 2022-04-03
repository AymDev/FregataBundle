<?php

namespace Tests\Fregata\FregataBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Fregata\FregataBundle\Doctrine\Migration\MigrationEntity;
use Psr\Container\ContainerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * @internal
 */
abstract class AbstractFunctionalTestCase extends WebTestCase
{
    protected KernelBrowser $client;
    protected EntityManagerInterface $entityManager;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient();
        /** @var ContainerInterface $container */
        $container = $this->client->getContainer();

        /** @var EntityManagerInterface $manager */
        $manager = $container->get('doctrine')->getManager();
        $this->entityManager = $manager;
    }

    /**
     * @inheritDoc
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        // Close the Entity Manager
        $this->entityManager->close();
    }

    protected function createMigrationEntity(
        string $status = MigrationEntity::STATUS_CREATED,
        string $serviceId = 'test_migration'
    ): MigrationEntity {
        $migration = (new MigrationEntity())
            ->setStatus($status)
            ->setServiceId($serviceId);

        $this->entityManager->persist($migration);
        $this->entityManager->flush();
        return $migration;
    }
}
