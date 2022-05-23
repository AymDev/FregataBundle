<?php

namespace Tests\Fregata\FregataBundle\Messenger\Command\Migrator;

use Fregata\FregataBundle\Doctrine\ComponentStatus;
use Fregata\FregataBundle\Doctrine\Migration\MigrationEntity;
use Fregata\FregataBundle\Doctrine\Migration\MigrationStatus;
use Fregata\FregataBundle\Doctrine\Migrator\MigratorEntity;
use Fregata\FregataBundle\Messenger\Command\Migrator\MigratorNotReadyException;
use Tests\Fregata\FregataBundle\Messenger\AbstractMessengerTestCase;

class MigratorNotReadyExceptionTest extends AbstractMessengerTestCase
{
    /**
     * The migrator must have a migration as its readiness depends on it.
     */
    public function testMigratorMustHaveAMigration(): void
    {
        // Migrator creation
        $migrator = new MigratorEntity();

        // Exception test
        self::expectException(\LogicException::class);
        new MigratorNotReadyException($migrator);
    }

    /**
     * The exception message must contain important information
     */
    public function testExceptionMessageContent(): void
    {
        // Migrator creation
        $migrator = (new MigratorEntity())
            ->setServiceId('migrator.id')
            ->setStatus(ComponentStatus::RUNNING);

        $migration = (new MigrationEntity())
            ->setServiceId('migration.id')
            ->addMigrator($migrator)
            ->setStatus(MigrationStatus::MIGRATORS);

        $this->getEntityManager()->persist($migration);
        $this->getEntityManager()->persist($migrator);
        $this->getEntityManager()->flush();

        // Exception test
        $exception = new MigratorNotReadyException($migrator);
        $message = $exception->getMessage();

        self::assertStringContainsString((string)$migrator->getId(), $message);
        self::assertStringContainsString($migrator->getStatus()->value, $message);
        self::assertInstanceOf(MigrationEntity::class, $migrator->getMigration());
        self::assertStringContainsString($migrator->getMigration()->getStatus()->value, $message);
    }
}
