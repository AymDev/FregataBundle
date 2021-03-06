<?php

namespace Tests\Fregata\FregataBundle\Messenger\Command\Task;

use Doctrine\ORM\EntityManagerInterface;
use Fregata\Adapter\Doctrine\DBAL\ForeignKey\Task\ForeignKeyAfterTask;
use Fregata\Adapter\Doctrine\DBAL\ForeignKey\Task\ForeignKeyBeforeTask;
use Fregata\FregataBundle\Doctrine\ComponentStatus;
use Fregata\FregataBundle\Doctrine\Migration\MigrationEntity;
use Fregata\FregataBundle\Doctrine\Migration\MigrationStatus;
use Fregata\FregataBundle\Doctrine\Migrator\MigratorEntity;
use Fregata\FregataBundle\Doctrine\Task\TaskEntity;
use Fregata\FregataBundle\Doctrine\Task\TaskRepository;
use Fregata\FregataBundle\Doctrine\Task\TaskType;
use Fregata\FregataBundle\Messenger\Command\Migrator\RunMigrator;
use Fregata\FregataBundle\Messenger\Command\Task\RunTask;
use Fregata\FregataBundle\Messenger\Command\Task\RunTaskHandler;
use Fregata\Migration\TaskInterface;
use Psr\Log\NullLogger;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Tests\Fregata\FregataBundle\Messenger\AbstractMessengerTestCase;

class RunTaskHandlerTest extends AbstractMessengerTestCase
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

    private function createMigratorEntity(MigrationEntity $migration): MigratorEntity
    {
        $migrator = (new MigratorEntity())->setServiceId('migrator_service');
        $migration->addMigrator($migrator);

        $this->getEntityManager()->persist($migration);
        $this->getEntityManager()->persist($migrator);
        $this->getEntityManager()->flush();

        return $migrator;
    }

    /**
     * Create a migration with a task and return a handler
     * @return array{RunTaskHandler, TaskEntity, MigrationEntity}
     */
    private function createHandlerWithEntities(
        MigrationStatus $migrationStatus,
        TaskType $taskType,
        ComponentStatus $taskStatus = ComponentStatus::CREATED
    ): array {
        $migration = (new MigrationEntity())
            ->setStatus($migrationStatus)
            ->setServiceId('migration_service');

        $task = $this->createTaskEntity($migration, $taskType, $taskStatus);

        /** @var TaskRepository $taskRepository */
        $taskRepository = self::getContainer()->get(TaskRepository::class);

        $handler = new RunTaskHandler(
            new ServiceLocator($this->servicesForLocator),
            $this->getEntityManager(),
            $taskRepository,
            $this->getMessageBus(),
            $this->logger,
        );

        return [$handler, $task, $migration];
    }

    /**
     * Unknown task ID must trigger an error
     */
    public function testFailOnUnknownTask(): void
    {
        $handler = new RunTaskHandler(
            new ServiceLocator([]),
            self::createMock(EntityManagerInterface::class),
            self::createMock(TaskRepository::class),
            self::createMock(MessageBusInterface::class),
            new NullLogger()
        );

        self::expectException(\RuntimeException::class);
        self::expectExceptionMessage('Unknown task ID.');

        $message = new RunTask(0);
        $handler($message);
    }

    /**
     * Task without migration must trigger an error
     */
    public function testFailOnMissingMigration(): void
    {
        $taskRepository = self::createMock(TaskRepository::class);
        $taskRepository->method('find')->willReturn(new TaskEntity());

        $handler = new RunTaskHandler(
            new ServiceLocator([]),
            self::createMock(EntityManagerInterface::class),
            $taskRepository,
            self::createMock(MessageBusInterface::class),
            new NullLogger()
        );

        self::expectException(\RuntimeException::class);
        self::expectExceptionMessage('Task has no migration.');

        $message = new RunTask(0);
        $handler($message);
    }

    /**
     * Task must not run multiple times
     */
    public function testRunTaskOnlyOnce(): void
    {
        $this->servicesForLocator = [
            'task_service' => function () {
                throw new \LogicException('Task must not run.');
            },
        ];

        [$handler, $task] = $this->createHandlerWithEntities(
            MigrationStatus::MIGRATORS,
            TaskType::BEFORE,
            ComponentStatus::RUNNING
        );

        $message = new RunTask((int)$task->getId());
        $handler($message);

        self::assertSame(ComponentStatus::RUNNING, $task->getStatus());
    }

    /**
     * Task must be cancel on migration failure/cancellation
     * @dataProvider provideCancellingMigrationStatuses
     */
    public function testCancelTaskOnInvalidMigrationStatus(MigrationStatus $migrationStatus): void
    {
        [$handler, $task] = $this->createHandlerWithEntities($migrationStatus, TaskType::BEFORE);

        $message = new RunTask((int)$task->getId());
        $handler($message);

        self::assertSame(ComponentStatus::CANCELED, $task->getStatus());
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
     * Invalid task/migration types combination must trigger an error
     * @dataProvider provideInvalidMigrationStatuses
     */
    public function testFailOnInvalidMigrationStatus(TaskType $taskType, MigrationStatus $migrationStatus): void
    {
        [$handler, $task] = $this->createHandlerWithEntities($migrationStatus, $taskType);

        self::expectException(\RuntimeException::class);
        self::expectExceptionMessage('Migration in invalid state to run task.');

        $message = new RunTask((int)$task->getId());
        $handler($message);
    }

    /**
     * @return array{TaskType, MigrationStatus}[]
     */
    public function provideInvalidMigrationStatuses(): array
    {
        return [
            [TaskType::BEFORE, MigrationStatus::MIGRATORS],
            [TaskType::BEFORE, MigrationStatus::CORE_AFTER_TASKS],
            [TaskType::BEFORE, MigrationStatus::AFTER_TASKS],
            [TaskType::BEFORE, MigrationStatus::FINISHED],
            [TaskType::AFTER, MigrationStatus::CREATED],
            [TaskType::AFTER, MigrationStatus::BEFORE_TASKS],
            [TaskType::AFTER, MigrationStatus::CORE_BEFORE_TASKS],
            [TaskType::AFTER, MigrationStatus::FINISHED],
        ];
    }

    /**
     * Core before tasks must wait for user before tasks to be completed
     */
    public function testCoreBeforeTaskRunAfterUserBeforeTask(): void
    {
        $this->servicesForLocator = [
            'task_service' => fn() => self::createMock(ForeignKeyBeforeTask::class),
            'user_task' => fn() => self::createMock(TaskInterface::class),
        ];

        [$handler, $task, $migration] = $this->createHandlerWithEntities(
            MigrationStatus::BEFORE_TASKS,
            TaskType::BEFORE
        );

        // Create a user defined before task
        $this->createTaskEntity($migration, TaskType::BEFORE, ComponentStatus::CREATED, 'user_task');

        $message = new RunTask((int)$task->getId());
        $handler($message);

        self::assertSame(MigrationStatus::BEFORE_TASKS, $migration->getStatus());
        self::assertArrayHasKey('notice', $this->logger->entries);
        self::assertCount(1, $this->logger->entries['notice']);
        self::assertMatchesRegularExpression(
            '~Task \d+ in \w+ status is not ready as the migration is in \w+ state~',
            $this->logger->entries['notice'][0]
        );
    }

    /**
     * User after tasks must wait for core after tasks to be completed
     */
    public function testUserAfterTaskRunAfterCoreAfterTask(): void
    {
        $this->servicesForLocator = [
            'task_service' => fn() => self::createMock(TaskInterface::class),
            'core_task' => fn() => self::createMock(ForeignKeyAfterTask::class),
        ];

        [$handler, $task, $migration] = $this->createHandlerWithEntities(
            MigrationStatus::CORE_AFTER_TASKS,
            TaskType::AFTER
        );

        // Create a core after task
        $this->createTaskEntity($migration, TaskType::AFTER, ComponentStatus::CREATED, 'core_task');

        $message = new RunTask((int)$task->getId());
        $handler($message);

        self::assertSame(MigrationStatus::CORE_AFTER_TASKS, $migration->getStatus());
        self::assertArrayHasKey('notice', $this->logger->entries);
        self::assertCount(1, $this->logger->entries['notice']);
        self::assertMatchesRegularExpression(
            '~Task \d+ in \w+ status is not ready as the migration is in \w+ state~',
            $this->logger->entries['notice'][0]
        );
    }

    /**
     * Migration status is updated to for core before tasks when no user tasks are left
     */
    public function testMigrationStatutIsUpdatedForCoreBeforeTasks(): void
    {
        $this->servicesForLocator = [
            'task_service' => fn() => self::createMock(ForeignKeyBeforeTask::class),
            'user_task' => fn() => self::createMock(TaskInterface::class),
        ];

        [$handler, $task, $migration] = $this->createHandlerWithEntities(
            MigrationStatus::BEFORE_TASKS,
            TaskType::BEFORE
        );

        // Create a user defined before task
        $this->createTaskEntity($migration, TaskType::BEFORE, ComponentStatus::FINISHED, 'user_task');

        $message = new RunTask((int)$task->getId());
        $handler($message);

        self::assertSame(MigrationStatus::CORE_BEFORE_TASKS, $migration->getStatus());
        self::assertArrayHasKey('info', $this->logger->entries);
        self::assertCount(1, $this->logger->entries['info']);
        self::assertMatchesRegularExpression(
            '~Migration reached the \w+ status~',
            $this->logger->entries['info'][0]
        );
    }

    /**
     * Migration status is updated to for user after tasks when no core tasks are left
     */
    public function testMigrationStatutIsUpdatedForUserAfterTasks(): void
    {
        // The remaining user after task ensure the migration is not updated to a finished state
        $this->servicesForLocator = [
            'task_service' => fn() => self::createMock(TaskInterface::class),
            'core_task' => fn() => self::createMock(ForeignKeyAfterTask::class),
            'user_task' => fn() => self::createMock(TaskInterface::class),
        ];

        [$handler, $task, $migration] = $this->createHandlerWithEntities(
            MigrationStatus::CORE_AFTER_TASKS,
            TaskType::AFTER
        );

        // Create a finished core task
        $this->createTaskEntity($migration, TaskType::AFTER, ComponentStatus::FINISHED, 'core_task');
        // Create a user defined after task
        $this->createTaskEntity($migration, TaskType::AFTER, ComponentStatus::CREATED, 'user_task');

        $message = new RunTask((int)$task->getId());
        $handler($message);

        self::assertEquals(MigrationStatus::AFTER_TASKS, $migration->getStatus());
        self::assertArrayHasKey('info', $this->logger->entries);
        self::assertCount(1, $this->logger->entries['info']);
        self::assertMatchesRegularExpression(
            '~Migration reached the \w+ status~',
            $this->logger->entries['info'][0]
        );
    }

    /**
     * A failing task updates the task and migration to a failure state
     */
    public function testTaskFailure(): void
    {
        $this->servicesForLocator['task_service'] = function () {
            $task = self::createMock(TaskInterface::class);
            $task->method('execute')->willThrowException(new \Exception('task failure'));
            return $task;
        };

        [$handler, $task, $migration] = $this->createHandlerWithEntities(
            MigrationStatus::BEFORE_TASKS,
            TaskType::BEFORE
        );

        try {
            $message = new RunTask((int)$task->getId());
            $handler($message);

            throw new \Exception('A RuntimeException is expected to be thrown.');
        } catch (\RuntimeException $e) {
            self::assertSame('Task failed.', $e->getMessage());
        }

        self::assertSame(ComponentStatus::FAILURE, $task->getStatus());
        self::assertSame(MigrationStatus::FAILURE, $migration->getStatus());
    }

    /**
     * A successful task updates its status
     */
    public function testTaskSuccess(): void
    {
        $this->servicesForLocator['task_service'] = fn() => self::createMock(TaskInterface::class);

        [$handler, $task] = $this->createHandlerWithEntities(
            MigrationStatus::BEFORE_TASKS,
            TaskType::BEFORE
        );
        $message = new RunTask((int)$task->getId());
        $handler($message);

        self::assertSame(ComponentStatus::FINISHED, $task->getStatus());
    }

    /**
     * Task messages of the next step must not be dispatched if there are remaining tasks of the current step
     * @param class-string $currentTaskClass
     * @param class-string $nextTaskClass
     * @dataProvider provideNextTaskStepScenarios
     */
    public function testNextTaskNotDispatchedWhenRemainingTask(
        TaskType $taskType,
        string $currentTaskClass,
        string $nextTaskClass,
        MigrationStatus $migrationStatus
    ): void {
        $this->servicesForLocator = [
            'task_service' => fn() => self::createMock($currentTaskClass),
            'next_step_task' => fn() => self::createMock($nextTaskClass),
            'remaining_task' => fn() => self::createMock($currentTaskClass),
        ];

        [$handler, $task, $migration] = $this->createHandlerWithEntities($migrationStatus, $taskType);

        // Create a task of the next step
        $this->createTaskEntity($migration, $taskType, ComponentStatus::CREATED, 'next_step_task');
        // Create a remaining task of the current step
        $this->createTaskEntity($migration, $taskType, ComponentStatus::CREATED, 'remaining_task');

        $message = new RunTask((int)$task->getId());
        $handler($message);

        self::assertSame(ComponentStatus::FINISHED, $task->getStatus());
        self::assertCount(0, $this->getMessengerTransport()->get());
    }

    /**
     * Task messages of the next step must be dispatched if there are no remaining tasks of the current step
     * @param class-string $currentTaskClass
     * @param class-string $nextTaskClass
     * @dataProvider provideNextTaskStepScenarios
     */
    public function testNextTaskDispatchedWhenNoRemainingTask(
        TaskType $taskType,
        string $currentTaskClass,
        string $nextTaskClass,
        MigrationStatus $migrationStatus
    ): void {
        $this->servicesForLocator = [
            'task_service' => fn() => self::createMock($currentTaskClass),
            'next_step_task' => fn() => self::createMock($nextTaskClass),
        ];

        [$handler, $task, $migration] = $this->createHandlerWithEntities(
            $migrationStatus,
            $taskType
        );

        $nextTask = $this->createTaskEntity($migration, $taskType, ComponentStatus::CREATED, 'next_step_task');

        $message = new RunTask((int)$task->getId());
        $handler($message);

        self::assertSame(ComponentStatus::FINISHED, $task->getStatus());

        /** @var Envelope[] $envelopes */
        $envelopes = $this->getMessengerTransport()->get();
        self::assertCount(1, $envelopes);

        $message = $envelopes[0]->getMessage();
        self::assertInstanceOf(RunTask::class, $message);
        self::assertSame($nextTask->getId(), $message->getTaskId());
    }

    /**
     * @return array{TaskType, class-string<TaskInterface>, class-string<TaskInterface>, MigrationStatus}[]
     */
    public function provideNextTaskStepScenarios(): array
    {
        return [
            [
                TaskType::BEFORE,
                TaskInterface::class,
                ForeignKeyBeforeTask::class,
                MigrationStatus::BEFORE_TASKS
            ],
            [
                TaskType::AFTER,
                ForeignKeyAfterTask::class,
                TaskInterface::class,
                MigrationStatus::CORE_AFTER_TASKS
            ],
        ];
    }

    /**
     * Migrator messages must not be dispatched if there are remaining user before tasks
     * @param class-string $taskClass
     * @dataProvider provideMigratorMessageDispatchingScenarios
     */
    public function testMigratorNotDispatchedWhenRemainingBeforeTask(
        string $taskClass,
        MigrationStatus $migrationStatus
    ): void {
        $this->servicesForLocator = [
            'task_service' => fn() => self::createMock($taskClass),
            'remaining_task' => fn() => self::createMock($taskClass),
        ];

        [$handler, $task, $migration] = $this->createHandlerWithEntities(
            $migrationStatus,
            TaskType::BEFORE
        );

        // Create a remaining before task
        $this->createTaskEntity($migration, TaskType::BEFORE, ComponentStatus::CREATED, 'remaining_task');
        // Create a migrator
        $this->createMigratorEntity($migration);

        $message = new RunTask((int)$task->getId());
        $handler($message);

        self::assertSame(ComponentStatus::FINISHED, $task->getStatus());
        self::assertCount(0, $this->getMessengerTransport()->get());
    }

    /**
     * Migrator messages must be dispatched if there are no remaining before tasks
     * @param class-string $taskClass
     * @dataProvider provideMigratorMessageDispatchingScenarios
     */
    public function testMigratorDispatchedWhenNoRemainingBeforeTask(
        string $taskClass,
        MigrationStatus $migrationStatus
    ): void {
        $this->servicesForLocator = [
            'task_service' => fn() => self::createMock($taskClass),
        ];

        [$handler, $task, $migration] = $this->createHandlerWithEntities(
            $migrationStatus,
            TaskType::BEFORE
        );

        // Create a migrator
        $this->createMigratorEntity($migration);

        $message = new RunTask((int)$task->getId());
        $handler($message);

        self::assertSame(ComponentStatus::FINISHED, $task->getStatus());

        /** @var Envelope[] $envelopes */
        $envelopes = $this->getMessengerTransport()->get();
        self::assertCount(1, $envelopes);

        $message = $envelopes[0]->getMessage();
        self::assertInstanceOf(RunMigrator::class, $message);
    }

    /**
     * @return array{class-string<TaskInterface>, MigrationStatus}[]
     */
    public function provideMigratorMessageDispatchingScenarios(): array
    {
        return [
            [TaskInterface::class, MigrationStatus::BEFORE_TASKS],
            [ForeignKeyBeforeTask::class, MigrationStatus::CORE_BEFORE_TASKS],
        ];
    }

    /**
     * Migration must not be finished if there are remaining after tasks
     * @param class-string $taskClass
     * @dataProvider provideFinishedMigrationScenarios
     */
    public function testMigrationNotFinishedWhenRemainingAfterTask(
        string $taskClass,
        MigrationStatus $migrationStatus
    ): void {
        $this->servicesForLocator = [
            'task_service' => fn() => self::createMock($taskClass),
            'remaining_task' => fn() => self::createMock($taskClass),
        ];

        [$handler, $task, $migration] = $this->createHandlerWithEntities(
            $migrationStatus,
            TaskType::AFTER
        );

        // Create a remaining task
        $this->createTaskEntity($migration, TaskType::AFTER, ComponentStatus::CREATED, 'remaining_task');

        $message = new RunTask((int)$task->getId());
        $handler($message);

        self::assertSame(ComponentStatus::FINISHED, $task->getStatus());
        self::assertEquals($migrationStatus, $migration->getStatus());
    }

    /**
     * Migration must be finished if there are no remaining core tasks
     * @param class-string $taskClass
     * @dataProvider provideFinishedMigrationScenarios
     */
    public function testMigrationFinishedWhenNoRemainingAfterTask(
        string $taskClass,
        MigrationStatus $migrationStatus
    ): void {
        $this->servicesForLocator = [
            'task_service' => fn() => self::createMock($taskClass),
        ];

        [$handler, $task, $migration] = $this->createHandlerWithEntities(
            $migrationStatus,
            TaskType::AFTER
        );

        $message = new RunTask((int)$task->getId());
        $handler($message);

        self::assertSame(ComponentStatus::FINISHED, $task->getStatus());
        self::assertSame(MigrationStatus::FINISHED, $migration->getStatus());
    }

    /**
     * @return array{class-string<TaskInterface>, MigrationStatus}[]
     */
    public function provideFinishedMigrationScenarios(): array
    {
        return [
            [TaskInterface::class, MigrationStatus::AFTER_TASKS],
            [ForeignKeyAfterTask::class, MigrationStatus::CORE_AFTER_TASKS],
        ];
    }
}
