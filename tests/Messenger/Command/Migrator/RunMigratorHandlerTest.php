<?php

namespace Tests\Fregata\FregataBundle\Messenger\Command\Migrator;

use Doctrine\ORM\EntityManagerInterface;
use Fregata\FregataBundle\Doctrine\Migration\MigrationEntity;
use Fregata\FregataBundle\Doctrine\Migrator\MigratorEntity;
use Fregata\FregataBundle\Doctrine\Migrator\MigratorRepository;
use Fregata\FregataBundle\Doctrine\Task\TaskEntity;
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
        string $taskType,
        string $taskStatus = TaskEntity::STATUS_CREATED,
        string $serviceId = 'task_service'
    ): TaskEntity {
        $task = (new TaskEntity())
            ->setType($taskType)
            ->setStatus($taskStatus)
            ->setServiceId($serviceId);

        $migration->addTask($task);

        $this->getEntityManager()->persist($migration);
        $this->getEntityManager()->persist($task);
        $this->getEntityManager()->flush();

        return $task;
    }

    private function createMigratorEntity(
        MigrationEntity $migration,
        string $status = MigratorEntity::STATUS_CREATED
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
        string $migrationStatus,
        string $migratorStatus = MigratorEntity::STATUS_CREATED
    ): array {
        $migration = (new MigrationEntity())
            ->setStatus($migrationStatus)
            ->setServiceId('migration_service');

        $migrator = $this->createMigratorEntity($migration, $migratorStatus);

        $handler = new RunMigratorHandler(
            new ServiceLocator($this->servicesForLocator),
            $this->getEntityManager(),
            $this->getEntityManager()->getRepository(MigratorEntity::class),
            $this->getMessageBus(),
            $this->getLogger(),
        );

        return [$handler, $migrator, $migration];
    }

    /**
     * Unknown migrator ID must trigger an error
     */
    public function testFailOnUnknownMigrator(): void
    {
        $migrator = self::createMock(MigratorEntity::class);
        $migrator->expects(self::once())
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
            MigrationEntity::STATUS_MIGRATORS,
            MigratorEntity::STATUS_RUNNING
        );

        $message = new RunMigrator($migrator);
        $handler($message);

        self::assertSame(MigratorEntity::STATUS_RUNNING, $migrator->getStatus());
    }

    /**
     * Migrator must be cancel on migration failure/cancellation
     * @dataProvider provideCancellingMigrationStatuses
     */
    public function testCancelMigratorOnInvalidMigrationStatus(string $migrationStatus): void
    {
        [$handler, $migrator] = $this->createHandlerWithEntities($migrationStatus);

        $message = new RunMigrator($migrator);
        $handler($message);

        self::assertSame(MigratorEntity::STATUS_CANCELED, $migrator->getStatus());
    }

    /**
     * @return string[][]
     */
    public function provideCancellingMigrationStatuses(): array
    {
        return [
            [MigrationEntity::STATUS_CANCELED],
            [MigrationEntity::STATUS_FAILURE],
        ];
    }

    /**
     * Invalid migration status must trigger an error
     * @dataProvider provideInvalidMigrationStatuses
     */
    public function testFailOnInvalidMigrationStatus(string $migrationStatus): void
    {
        [$handler, $migrator] = $this->createHandlerWithEntities($migrationStatus);

        self::expectException(\RuntimeException::class);
        self::expectExceptionMessage('Migration in invalid state to run task.');

        $message = new RunMigrator($migrator);
        $handler($message);
    }

    /**
     * @return string[][]
     */
    public function provideInvalidMigrationStatuses(): array
    {
        return [
            [MigrationEntity::STATUS_CORE_AFTER_TASKS],
            [MigrationEntity::STATUS_AFTER_TASKS],
            [MigrationEntity::STATUS_FINISHED],
        ];
    }

    /**
     * All before tasks must be completed before running a migrator
     */
    public function testMigratorWaitsForBeforeTasks(): void
    {
        [$handler, $migrator, $migration] = $this->createHandlerWithEntities(MigrationEntity::STATUS_BEFORE_TASKS);

        // Create a before task
        $this->createTaskEntity($migration, TaskEntity::TASK_BEFORE, TaskEntity::STATUS_RUNNING);

        $message = new RunMigrator($migrator);
        $handler($message);

        self::assertSame(MigrationEntity::STATUS_BEFORE_TASKS, $migration->getStatus());
        self::assertTrue($this->getLogger()->hasNoticeThatMatches(
            '~Migrator \d+ in \w+ status is not ready as the migration is in \w+ state~'
        ));
    }

    /**
     * All previous migrators must be completed before running a dependent migrator
     */
    public function testDependentMigratorWaitsForPreviousMigrators(): void
    {
        [$handler, $migrator, $migration] = $this->createHandlerWithEntities(MigrationEntity::STATUS_MIGRATORS);

        // Create a previous migrator
        $migrator->addPreviousMigrator($this->createMigratorEntity($migration));

        $message = new RunMigrator($migrator);
        $handler($message);

        self::assertSame(MigrationEntity::STATUS_MIGRATORS, $migration->getStatus());
        self::assertTrue($this->getLogger()->hasNoticeThatMatches(
            '~Migrator \d+ in \w+ status is not ready as the migration is in \w+ state~'
        ));
    }

    /**
     * Migration status is updated for migrators when no before tasks are left
     */
    public function testMigrationStatutIsUpdatedForMigrators(): void
    {
        $this->servicesForLocator = [
            'migrator_service' => fn() => self::createMock(MigratorInterface::class),
        ];

        [$handler, $migrator, $migration] = $this->createHandlerWithEntities(MigrationEntity::STATUS_BEFORE_TASKS);

        // Create tasks
        $this->createTaskEntity($migration, TaskEntity::TASK_BEFORE, TaskEntity::STATUS_FINISHED);
        $this->createTaskEntity($migration, TaskEntity::TASK_AFTER);

        $message = new RunMigrator($migrator);
        $handler($message);

        self::assertSame(MigrationEntity::STATUS_MIGRATORS, $migration->getStatus());
        self::assertTrue($this->getLogger()->hasInfoThatMatches('~Migration reached the \w+ status~'));
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

        [$handler, $migrator, $migration] = $this->createHandlerWithEntities(MigrationEntity::STATUS_BEFORE_TASKS);

        try {
            $message = new RunMigrator($migrator);
            $handler($message);

            throw new \Exception('A RuntimeException is expected to be thrown.');
        } catch (\RuntimeException $e) {
            self::assertSame('Migrator failed.', $e->getMessage());
        }

        self::assertSame(MigratorEntity::STATUS_FAILURE, $migrator->getStatus());
        self::assertSame(MigrationEntity::STATUS_FAILURE, $migration->getStatus());
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

        [$handler, $migrator] = $this->createHandlerWithEntities(MigrationEntity::STATUS_MIGRATORS);
        $message = new RunMigrator($migrator);
        $handler($message);

        self::assertSame(MigratorEntity::STATUS_FINISHED, $migrator->getStatus());
    }

    /**
     * Migrator with dependencies must dispatch messages for the next migrators
     */
    public function testMigratorDispatchNextMigratorMessages(): void
    {
        $this->servicesForLocator['migrator_service'] = fn() => self::createMock(MigratorInterface::class);

        [$handler, $migrator, $migration] = $this->createHandlerWithEntities(MigrationEntity::STATUS_MIGRATORS);

        // Add next migrator
        $migrator->addNextMigrator($this->createMigratorEntity($migration));

        $message = new RunMigrator($migrator);
        $handler($message);

        self::assertSame(MigratorEntity::STATUS_FINISHED, $migrator->getStatus());
        self::assertCount(1, $this->getMessengerTransport()->get());
    }

    /**
     * After task messages must not be dispatched if other migrators are remaining
     */
    public function testAfterTaskMessagesNotDispatchedOnRemainingMigrators(): void
    {
        $this->servicesForLocator['migrator_service'] = fn() => self::createMock(MigratorInterface::class);

        [$handler, $migrator, $migration] = $this->createHandlerWithEntities(MigrationEntity::STATUS_MIGRATORS);

        // Add migrator & task
        $this->createMigratorEntity($migration);
        $this->createTaskEntity($migration, TaskEntity::TASK_AFTER);

        $message = new RunMigrator($migrator);
        $handler($message);

        self::assertSame(MigratorEntity::STATUS_FINISHED, $migrator->getStatus());
        self::assertCount(0, $this->getMessengerTransport()->get());
    }

    /**
     * After task messages must be dispatched if no migrators are remaining
     */
    public function testAfterTaskMessagesDispatchedOnNoRemainingMigrators(): void
    {
        $this->servicesForLocator['migrator_service'] = fn() => self::createMock(MigratorInterface::class);

        [$handler, $migrator, $migration] = $this->createHandlerWithEntities(MigrationEntity::STATUS_MIGRATORS);

        // Add task
        $this->createTaskEntity($migration, TaskEntity::TASK_AFTER);

        $message = new RunMigrator($migrator);
        $handler($message);

        self::assertSame(MigratorEntity::STATUS_FINISHED, $migrator->getStatus());
        self::assertSame(MigrationEntity::STATUS_MIGRATORS, $migration->getStatus());
        self::assertCount(1, $this->getMessengerTransport()->get());
    }

    /**
     * Migration is finished if there is no remaining migrators or after tasks
     */
    public function testMigrationFinishedOnNoRemainingMigratorsOrAfterTasks(): void
    {
        $this->servicesForLocator['migrator_service'] = fn() => self::createMock(MigratorInterface::class);

        [$handler, $migrator, $migration] = $this->createHandlerWithEntities(MigrationEntity::STATUS_MIGRATORS);

        $message = new RunMigrator($migrator);
        $handler($message);

        self::assertSame(MigratorEntity::STATUS_FINISHED, $migrator->getStatus());
        self::assertSame(MigrationEntity::STATUS_FINISHED, $migration->getStatus());
    }
}
