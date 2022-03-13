<?php

namespace Tests\Fregata\FregataBundle\Messenger\Command\Migrator;

use Fregata\FregataBundle\Doctrine\Migration\MigrationEntity;
use Fregata\FregataBundle\Doctrine\Migrator\MigratorEntity;
use Fregata\FregataBundle\Messenger\Command\Migrator\MigratorNotReadyException;
use Tests\Fregata\FregataBundle\Messenger\AbstractMessengerTestCase;

class MigratorNotReadyExceptionTest extends AbstractMessengerTestCase
{
    /**
     * The exception message must contain important information
     */
    public function testExceptionMessageContent()
    {
        // Migrator creation
        $migrator = (new MigratorEntity())
            ->setServiceId('migrator.id')
            ->setStatus(MigratorEntity::STATUS_RUNNING);

        $migration = (new MigrationEntity())
            ->setServiceId('migration.id')
            ->addMigrator($migrator)
            ->setStatus(MigrationEntity::STATUS_MIGRATORS);

        $this->getEntityManager()->persist($migration);
        $this->getEntityManager()->persist($migrator);
        $this->getEntityManager()->flush();

        // Exception test
        $exception = new MigratorNotReadyException($migrator);
        $message = $exception->getMessage();

        self::assertStringContainsString($migrator->getId(), $message);
        self::assertStringContainsString($migrator->getStatus(), $message);
        self::assertStringContainsString($migrator->getMigration()->getStatus(), $message);
    }
}
