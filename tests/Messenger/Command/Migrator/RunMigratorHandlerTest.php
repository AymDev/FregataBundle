<?php

namespace Tests\Fregata\FregataBundle\Messenger\Command\Migrator;

use Doctrine\ORM\EntityManagerInterface;
use Fregata\FregataBundle\Doctrine\ComponentStatus;
use Fregata\FregataBundle\Doctrine\Migration\MigrationEntity;
use Fregata\FregataBundle\Doctrine\Migration\MigrationStatus;
use Fregata\FregataBundle\Doctrine\Migrator\MigratorEntity;
use Fregata\FregataBundle\Doctrine\Migrator\MigratorRepository;
use Fregata\FregataBundle\Doctrine\Task\TaskEntity;
use Fregata\FregataBundle\Doctrine\Task\TaskType;
use Fregata\FregataBundle\Messenger\Command\Migrator\MigratorNotReadyException;
use Fregata\FregataBundle\Messenger\Command\Migrator\RunMigrator;
use Fregata\FregataBundle\Messenger\Command\Migrator\RunMigratorHandler;
use Fregata\Migration\Migrator\Component\Executor;
use Fregata\Migration\Migrator\Component\PullerInterface;
use Fregata\Migration\Migrator\Component\PusherInterface;
use Fregata\Migration\Migrator\MigratorInterface;
use Psr\Log\NullLogger;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Messenger\MessageBusInterface;
use Tests\Fregata\FregataBundle\Messenger\AbstractMessengerTestCase;

class RunMigratorHandlerTest extends AbstractMessengerTestCase
{
    /** @var callable[] */
    private array $servicesForLocator = [];

    /**
     * Reset the service factories for the service locator
     */
    protected function tearDown(): void
    {
        parent::tearDown();
        $this->servicesForLocator = [];
    }

    private function createTaskEntity(
        MigrationEntity $migration,
        TaskType $taskType,
        ComponentStatus $taskStatus = ComponentStatus::CREATED,
    ): TaskEntity {
        $task = (new TaskEntity())
            ->setType($taskType)
            ->setStatus($taskStatus)
            ->setServiceId('task_service');

        $migration->addTask($task);

        $this->getEntityManager()->persist($migration);
        $this->getEntityManager()->persist($task);
        $this->getEntityManager()->flush();

        return $task;
    }

    private function createMigratorEntity(
        MigrationEntity $migration,
        ComponentStatus $status = ComponentStatus::CREATED
    ): MigratorEntity {
        $migrator = (new MigratorEntity())
            ->setServiceId('migrator_service')
            ->setStatus($status)
        ;
        $migration->addMigrator($migrator);

        $this->getEntityManager()->persist($migration);
        $this->getEntityManager()->persist($migrator);
        $this->getEntityManager()->flush();

        return $migrator;
    }

    /**
     * Create a migration with a migrator and return a handler
     * @return array{RunMigratorHandler, MigratorEntity, MigrationEntity}
     */
    private function createHandlerWithEntities(
        MigrationStatus $migrationStatus,
        ComponentStatus $migratorStatus = ComponentStatus::CREATED
    ): array {
        $migration = (new MigrationEntity())
            ->setStatus($migrationStatus)
            ->setServiceId('migration_service');

        $migrator = $this->createMigratorEntity($migration, $migratorStatus);

        /** @var MigratorRepository $migratorRepository */
        $migratorRepository = self::getContainer()->get(MigratorRepository::class);

        $handler = new RunMigratorHandler(
            new ServiceLocator($this->servicesForLocator),
            $this->getEntityManager(),
            $migratorRepository,
            $this->getMessageBus(),
            $this->logger,
        );

        return [$handler, $migrator, $migration];
    }

    /**
     * Unknown migrator ID must trigger an error
     */
    public function testFailOnMissingMigration(): void
    {
        $migrator = self::createMock(MigratorEntity::class);
        $migrator->expects(self::atLeastOnce())
            ->method('getId')
            ->willReturn(42);

        $migratorRepository = self::createMock(MigratorRepository::class);
        $migratorRepository->method('find')->willReturn($migrator);

        $handler = new RunMigratorHandler(
            new ServiceLocator([]),
            self::createMock(EntityManagerInterface::class),
            $migratorRepository,
            self::createMock(MessageBusInterface::class),
            new NullLogger()
        );

        self::expectException(\RuntimeException::class);
        self::expectExceptionMessage('Migrator has no migration.');

        $message = new RunMigrator($migrator);
        $handler($message);
    }

    /**
     * Unknown migrator ID must trigger an error
     */
    public function testFailOnUnknownMigrator(): void
    {
        $migrator = self::createMock(MigratorEntity::class);
        $migrator->expects(self::atLeastOnce())
            ->method('getId')
            ->willReturn(42);

        $handler = new RunMigratorHandler(
            new ServiceLocator([]),
            self::createMock(EntityManagerInterface::class),
            self::createMock(MigratorRepository::class),
            self::createMock(MessageBusInterface::class),
            new NullLogger()
        );

        self::expectException(\RuntimeException::class);
        self::expectExceptionMessage('Unknown migrator ID.');

        $message = new RunMigrator($migrator);
        $handler($message);
    }

    /**
     * Migrator must not run multiple times
     */
    public function testRunMigratorOnlyOnce(): void
    {
        $this->servicesForLocator = [
            'migrator_service' => function () {
                throw new \LogicException('Migrator must not run.');
            },
        ];

        [$handler, $migrator] = $this->createHandlerWithEntities(
            MigrationStatus::MIGRATORS,
            ComponentStatus::RUNNING
        );

        $message = new RunMigrator($migrator);
        $handler($message);

        self::assertSame(ComponentStatus::RUNNING, $migrator->getStatus());
    }

    /**
     * Migrator must be cancel on migration failure/cancellation
     * @dataProvider provideCancellingMigrationStatuses
     */
    public function testCancelMigratorOnInvalidMigrationStatus(MigrationStatus $migrationStatus): void
    {
        [$handler, $migrator] = $this->createHandlerWithEntities($migrationStatus);

        $message = new RunMigrator($migrator);
        $handler($message);

        self::assertSame(ComponentStatus::CANCELED, $migrator->getStatus());
    }

    /**
     * @return MigrationStatus[][]
     */
    public function provideCancellingMigrationStatuses(): array
    {
        return [
            [MigrationStatus::CANCELED],
            [MigrationStatus::FAILURE],
        ];
    }

    /**
     * Invalid migration status must trigger an error
     * @dataProvider provideInvalidMigrationStatuses
     */
    public function testFailOnInvalidMigrationStatus(MigrationStatus $migrationStatus): void
    {
        [$handler, $migrator] = $this->createHandlerWithEntities($migrationStatus);

        self::expectException(\RuntimeException::class);
        self::expectExceptionMessage('Migration in invalid state to run migrator.');

        $message = new RunMigrator($migrator);
        $handler($message);
    }

    /**
     * @return MigrationStatus[][]
     */
    public function provideInvalidMigrationStatuses(): array
    {
        return [
            [MigrationStatus::CORE_AFTER_TASKS],
            [MigrationStatus::AFTER_TASKS],
            [MigrationStatus::FINISHED],
        ];
    }

    /**
     * All before tasks must be completed before running a migrator
     */
    public function testMigratorWaitsForBeforeTasks(): void
    {
        [$handler, $migrator, $migration] = $this->createHandlerWithEntities(MigrationStatus::BEFORE_TASKS);

        // Create a before task
        $this->createTaskEntity($migration, TaskType::BEFORE, ComponentStatus::RUNNING);

        $message = new RunMigrator($migrator);
        $handler($message);

        self::assertSame(MigrationStatus::BEFORE_TASKS, $migration->getStatus());
        self::assertArrayHasKey('notice', $this->logger->entries);
        self::assertCount(1, $this->logger->entries['notice']);
        self::assertMatchesRegularExpression(
            '~Migrator \d+ in \w+ status is not ready as the migration is in \w+ state~',
            $this->logger->entries['notice'][0]
        );
    }

    /**
     * All previous migrators must be completed before running a dependent migrator
     */
    public function testDependentMigratorWaitsForPreviousMigrators(): void
    {
        [$handler, $migrator, $migration] = $this->createHandlerWithEntities(MigrationStatus::MIGRATORS);

        // Create a previous migrator
        $migrator->addPreviousMigrator($this->createMigratorEntity($migration));

        $message = new RunMigrator($migrator);
        $handler($message);

        self::assertSame(MigrationStatus::MIGRATORS, $migration->getStatus());
        self::assertArrayHasKey('notice', $this->logger->entries);
        self::assertCount(1, $this->logger->entries['notice']);
        self::assertMatchesRegularExpression(
            '~Migrator \d+ in \w+ status is not ready as the migration is in \w+ state~',
            $this->logger->entries['notice'][0]
        );
    }

    /**
     * Migration status is updated for migrators when no before tasks are left
     */
    public function testMigrationStatutIsUpdatedForMigrators(): void
    {
        $this->servicesForLocator = [
            'migrator_service' => fn() => self::createMock(MigratorInterface::class),
        ];

        [$handler, $migrator, $migration] = $this->createHandlerWithEntities(MigrationStatus::BEFORE_TASKS);

        // Create tasks
        $this->createTaskEntity($migration, TaskType::BEFORE, ComponentStatus::FINISHED);
        $this->createTaskEntity($migration, TaskType::AFTER);

        $message = new RunMigrator($migrator);
        $handler($message);

        self::assertSame(MigrationStatus::MIGRATORS, $migration->getStatus());
        self::assertArrayHasKey('info', $this->logger->entries);
        self::assertCount(1, $this->logger->entries['info']);
        self::assertMatchesRegularExpression(
            '~Migration reached the \w+ status~',
            $this->logger->entries['info'][0]
        );
    }

    /**
     * A failing migrator updates the migrator and migration to a failure state
     */
    public function testMigratorFailure(): void
    {
        $this->servicesForLocator['migrator_service'] = function () {
            $migrator = self::createMock(MigratorInterface::class);
            $migrator->method('getPuller')->willThrowException(new \Exception('migrator failure'));
            return $migrator;
        };

        [$handler, $migrator, $migration] = $this->createHandlerWithEntities(MigrationStatus::BEFORE_TASKS);

        try {
            $message = new RunMigrator($migrator);
            $handler($message);

            throw new \Exception('A RuntimeException is expected to be thrown.');
        } catch (\RuntimeException $e) {
            self::assertSame('Migrator failed.', $e->getMessage());
        }

        self::assertSame(ComponentStatus::FAILURE, $migrator->getStatus());
        self::assertSame(MigrationStatus::FAILURE, $migration->getStatus());
    }

    /**
     * A successful migrator updates its status
     */
    public function testMigratorSuccess(): void
    {
        $puller = self::createMock(PullerInterface::class);
        $pusher = self::createMock(PusherInterface::class);
        $executor = self::createMock(Executor::class);
        $executor->expects(self::atLeastOnce())
            ->method('execute')
            ->with($puller, $pusher);

        $migratorService = self::createMock(MigratorInterface::class);
        $migratorService->method('getPuller')->willReturn($puller);
        $migratorService->method('getPusher')->willReturn($pusher);
        $migratorService->method('getExecutor')->willReturn($executor);

        $this->servicesForLocator['migrator_service'] = fn() => $migratorService;

        [$handler, $migrator] = $this->createHandlerWithEntities(MigrationStatus::MIGRATORS);
        $message = new RunMigrator($migrator);
        $handler($message);

        self::assertSame(ComponentStatus::FINISHED, $migrator->getStatus());
    }

    /**
     * Migrator with dependencies must dispatch messages for the next migrators
     */
    public function testMigratorDispatchNextMigratorMessages(): void
    {
        $this->servicesForLocator['migrator_service'] = fn() => self::createMock(MigratorInterface::class);

        [$handler, $migrator, $migration] = $this->createHandlerWithEntities(MigrationStatus::MIGRATORS);

        // Add next migrator
        $migrator->addNextMigrator($this->createMigratorEntity($migration));

        $message = new RunMigrator($migrator);
        $handler($message);

        self::assertSame(ComponentStatus::FINISHED, $migrator->getStatus());
        self::assertCount(1, $this->getMessengerTransport()->get());
    }

    /**
     * After task messages must not be dispatched if other migrators are remaining
     */
    public function testAfterTaskMessagesNotDispatchedOnRemainingMigrators(): void
    {
        $this->servicesForLocator['migrator_service'] = fn() => self::createMock(MigratorInterface::class);

        [$handler, $migrator, $migration] = $this->createHandlerWithEntities(MigrationStatus::MIGRATORS);

        // Add migrator & task
        $this->createMigratorEntity($migration);
        $this->createTaskEntity($migration, TaskType::AFTER);

        $message = new RunMigrator($migrator);
        $handler($message);

        self::assertSame(ComponentStatus::FINISHED, $migrator->getStatus());
        self::assertCount(0, $this->getMessengerTransport()->get());
    }

    /**
     * After task messages must be dispatched if no migrators are remaining
     */
    public function testAfterTaskMessagesDispatchedOnNoRemainingMigrators(): void
    {
        $this->servicesForLocator['migrator_service'] = fn() => self::createMock(MigratorInterface::class);

        [$handler, $migrator, $migration] = $this->createHandlerWithEntities(MigrationStatus::MIGRATORS);

        // Add task
        $this->createTaskEntity($migration, TaskType::AFTER);

        $message = new RunMigrator($migrator);
        $handler($message);

        self::assertSame(ComponentStatus::FINISHED, $migrator->getStatus());
        self::assertSame(MigrationStatus::MIGRATORS, $migration->getStatus());
        self::assertCount(1, $this->getMessengerTransport()->get());
    }

    /**
     * Migration is finished if there is no remaining migrators or after tasks
     */
    public function testMigrationFinishedOnNoRemainingMigratorsOrAfterTasks(): void
    {
        $this->servicesForLocator['migrator_service'] = fn() => self::createMock(MigratorInterface::class);

        [$handler, $migrator, $migration] = $this->createHandlerWithEntities(MigrationStatus::MIGRATORS);

        $message = new RunMigrator($migrator);
        $handler($message);

        self::assertSame(ComponentStatus::FINISHED, $migrator->getStatus());
        self::assertSame(MigrationStatus::FINISHED, $migration->getStatus());
    }
}
