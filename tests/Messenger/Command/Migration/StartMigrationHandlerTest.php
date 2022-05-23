<?php

namespace Tests\Fregata\FregataBundle\Messenger\Command\Migration;

use Doctrine\ORM\EntityManagerInterface;
use Fregata\Adapter\Doctrine\DBAL\ForeignKey\Task\ForeignKeyBeforeTask;
use Fregata\FregataBundle\Doctrine\ComponentStatus;
use Fregata\FregataBundle\Doctrine\Migration\MigrationEntity;
use Fregata\FregataBundle\Doctrine\Migration\MigrationStatus;
use Fregata\FregataBundle\Doctrine\Migrator\MigratorEntity;
use Fregata\FregataBundle\Doctrine\Task\TaskEntity;
use Fregata\FregataBundle\Doctrine\Task\TaskType;
use Fregata\FregataBundle\Messenger\Command\Migration\StartMigration;
use Fregata\FregataBundle\Messenger\Command\Migration\StartMigrationHandler;
use Fregata\FregataBundle\Messenger\Command\Migrator\RunMigrator;
use Fregata\FregataBundle\Messenger\Command\Task\RunTask;
use Fregata\Migration\Migration;
use Fregata\Migration\MigrationRegistry;
use Fregata\Migration\Migrator\DependentMigratorInterface;
use Fregata\Migration\Migrator\MigratorInterface;
use Fregata\Migration\TaskInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Tests\Fregata\FregataBundle\Messenger\AbstractMessengerTestCase;

class StartMigrationHandlerTest extends AbstractMessengerTestCase
{
    /**
     * Aborts the migration if unknown
     */
    public function testThrowOnUnknownMigration(): void
    {
        self::expectException(\LogicException::class);

        $registry = new MigrationRegistry();
        $entityManager = self::createMock(EntityManagerInterface::class);
        $bus = self::createMock(MessageBusInterface::class);

        $message = new StartMigration('unknown');
        $handler = new StartMigrationHandler($registry, $entityManager, $bus);

        $handler($message);
    }

    /**
     * Migration, task and migrator entities must be persisted
     */
    public function testEntitiesArePersisted(): void
    {
        // Configure migration
        $migration = new Migration();
        $migration->add(self::createMock(MigratorInterface::class));
        $migration->addBeforeTask(self::createMock(TaskInterface::class));
        $migration->addAfterTask(self::createMock(TaskInterface::class));

        $registry = new MigrationRegistry();
        $registry->add('testing', $migration);

        // Handle message
        $message = new StartMigration('testing');
        $handler = new StartMigrationHandler($registry, $this->getEntityManager(), $this->getMessageBus());

        $handler($message);

        // Get entities
        $entities = $this->getEntityManager()->getUnitOfWork()->getIdentityMap();

        self::assertArrayHasKey(MigrationEntity::class, $entities);
        self::assertCount(1, $entities[MigrationEntity::class]);

        self::assertArrayHasKey(MigratorEntity::class, $entities);
        self::assertCount(1, $entities[MigratorEntity::class]);

        self::assertArrayHasKey(TaskEntity::class, $entities);
        self::assertCount(2, $entities[TaskEntity::class]);

        // Migration entity assertions
        /** @var MigrationEntity $migrationEntity */
        $migrationEntity = reset($entities[MigrationEntity::class]);

        self::assertSame('testing', $migrationEntity->getServiceId());
        self::assertSame(MigrationStatus::CREATED, $migrationEntity->getStatus());
        self::assertCount(1, $migrationEntity->getMigrators());
        self::assertCount(2, $migrationEntity->getTasks());

        // Migrator entity assertions
        /** @var MigratorEntity $migratorEntity */
        $migratorEntity = $migrationEntity->getMigrators()->first();
        self::assertIsString($migratorEntity->getServiceId());
        self::assertStringStartsWith('fregata.migration.testing.migrator.', $migratorEntity->getServiceId());
        self::assertSame(ComponentStatus::CREATED, $migratorEntity->getStatus());

        // Before task entity assertions
        $beforeTaskEntities = $migrationEntity->getTasks()
            ->filter(fn(TaskEntity $task) => $task->getType() === TaskType::BEFORE);
        self::assertCount(1, $beforeTaskEntities);

        /** @var TaskEntity $beforeTaskEntity */
        $beforeTaskEntity = $beforeTaskEntities->first();
        self::assertIsString($beforeTaskEntity->getServiceId());
        self::assertStringStartsWith('fregata.migration.testing.task.before.', $beforeTaskEntity->getServiceId());
        self::assertSame(ComponentStatus::CREATED, $beforeTaskEntity->getStatus());

        // After task entity assertions
        $afterTaskEntities = $migrationEntity->getTasks()
            ->filter(fn(TaskEntity $task) => $task->getType() === TaskType::AFTER);
        self::assertCount(1, $afterTaskEntities);

        /** @var TaskEntity $afterTaskEntity */
        $afterTaskEntity = $afterTaskEntities->first();
        self::assertIsString($afterTaskEntity->getServiceId());
        self::assertStringStartsWith('fregata.migration.testing.task.after.', $afterTaskEntity->getServiceId());
        self::assertSame(ComponentStatus::CREATED, $afterTaskEntity->getStatus());
    }

    /**
     * Before task messages must be dispatched if the migration have some
     */
    public function testBeforeTaskMessagesAreDispatched(): void
    {
        // Configure migration
        $migration = new Migration();
        $migration->add(self::createMock(MigratorInterface::class));
        $migration->addBeforeTask(self::createMock(TaskInterface::class));
        // This task must not trigger a message dispatch
        $migration->addAfterTask(self::createMock(TaskInterface::class));

        $registry = new MigrationRegistry();
        $registry->add('testing', $migration);

        // Handle message
        $message = new StartMigration('testing');
        $handler = new StartMigrationHandler($registry, $this->getEntityManager(), $this->getMessageBus());

        $handler($message);

        /** @var Envelope[] $messages */
        $messages = $this->getMessengerTransport()->getSent();
        self::assertCount(1, $messages);

        $message = $messages[0]->getMessage();
        self::assertInstanceOf(RunTask::class, $message);
    }

    /**
     * Only user defined before tasks messages are dispatched
     * when there is both user defined and core tasks in a migration
     */
    public function testOnlyUserBeforeTaskMessagesAreDispatched(): void
    {
        // Configure migration
        $migration = new Migration();
        $migration->add(self::createMock(MigratorInterface::class));
        $migration->addBeforeTask(self::createMock(TaskInterface::class));
        // Core task
        $migration->addBeforeTask(self::createMock(ForeignKeyBeforeTask::class));

        $registry = new MigrationRegistry();
        $registry->add('testing', $migration);

        // Handle message
        $message = new StartMigration('testing');
        $handler = new StartMigrationHandler($registry, $this->getEntityManager(), $this->getMessageBus());

        $handler($message);

        /** @var Envelope[] $messages */
        $messages = $this->getMessengerTransport()->getSent();
        self::assertCount(1, $messages);

        $message = $messages[0]->getMessage();
        self::assertInstanceOf(RunTask::class, $message);

        /** @var string $taskCount */
        $taskCount = $this->getEntityManager()
            ->getConnection()
            ->executeQuery('SELECT COUNT(*) FROM fregata_task')
            ->fetchOne()
        ;
        self::assertSame(2, (int)$taskCount);
    }

    /**
     * Core before tasks messages are dispatched when there is no user defined tasks in a migration
     */
    public function testCoreBeforeTaskMessagesAreDispatched(): void
    {
        // Configure migration
        $migration = new Migration();
        $migration->add(self::createMock(MigratorInterface::class));
        $migration->addBeforeTask(self::createMock(ForeignKeyBeforeTask::class));

        $registry = new MigrationRegistry();
        $registry->add('testing', $migration);

        // Handle message
        $message = new StartMigration('testing');
        $handler = new StartMigrationHandler($registry, $this->getEntityManager(), $this->getMessageBus());

        $handler($message);

        /** @var Envelope[] $messages */
        $messages = $this->getMessengerTransport()->getSent();
        self::assertCount(1, $messages);

        $message = $messages[0]->getMessage();
        self::assertInstanceOf(RunTask::class, $message);
    }

    /**
     * Migrator messages must be dispatched if there is no before task
     */
    public function testMigratorMessagesAreDispatched(): void
    {
        // Configure migration
        $migration = new Migration();
        $migration->add(self::createMock(MigratorInterface::class));

        $registry = new MigrationRegistry();
        $registry->add('testing', $migration);

        // Handle message
        $message = new StartMigration('testing');
        $handler = new StartMigrationHandler($registry, $this->getEntityManager(), $this->getMessageBus());

        $handler($message);

        /** @var Envelope[] $messages */
        $messages = $this->getMessengerTransport()->getSent();
        self::assertCount(1, $messages);

        $message = $messages[0]->getMessage();
        self::assertInstanceOf(RunMigrator::class, $message);
    }

    /**
     * Dependent migrators messages must not be dispatched
     */
    public function testOnlyIndependentMigratorMessagesAreDispatched(): void
    {
        // Configure migration
        $migration = new Migration();

        $migrator = self::createMock(MigratorInterface::class);
        $dependentMigrator = self::createMock(DependentMigratorInterface::class);
        $dependentMigrator->method('getDependencies')->willReturn([$migrator::class]);

        $migration->add($migrator);
        $migration->add($dependentMigrator);

        $registry = new MigrationRegistry();
        $registry->add('testing', $migration);

        // Handle message
        $message = new StartMigration('testing');
        $handler = new StartMigrationHandler($registry, $this->getEntityManager(), $this->getMessageBus());

        $handler($message);

        /** @var Envelope[] $messages */
        $messages = $this->getMessengerTransport()->getSent();
        self::assertCount(1, $messages);

        $message = $messages[0]->getMessage();
        self::assertInstanceOf(RunMigrator::class, $message);

        /** @var string $migratorsCount */
        $migratorsCount = $this->getEntityManager()
            ->getConnection()
            ->executeQuery('SELECT COUNT(*) FROM fregata_migrator')
            ->fetchOne()
        ;
        self::assertSame(2, (int)$migratorsCount);
    }
}
