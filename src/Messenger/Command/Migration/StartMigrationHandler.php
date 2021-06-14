<?php

namespace Fregata\FregataBundle\Messenger\Command\Migration;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Fregata\Adapter\Doctrine\DBAL\ForeignKey\Task\ForeignKeyBeforeTask;
use Fregata\FregataBundle\Doctrine\Migration\MigrationEntity;
use Fregata\FregataBundle\Doctrine\Migrator\MigratorEntity;
use Fregata\FregataBundle\Doctrine\Task\TaskEntity;
use Fregata\FregataBundle\Messenger\Command\Migrator\RunMigrator;
use Fregata\FregataBundle\Messenger\Command\Task\RunTask;
use Fregata\Migration\Migration;
use Fregata\Migration\MigrationRegistry;
use Fregata\Migration\Migrator\DependentMigratorInterface;
use Fregata\Migration\Migrator\MigratorInterface;
use Fregata\Migration\TaskInterface;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\String\UnicodeString;

/**
 * @internal
 * Create entities with related messages to run a migration.
 */
class StartMigrationHandler implements MessageHandlerInterface
{
    private MigrationRegistry $migrationRegistry;
    private EntityManagerInterface $entityManager;
    private MessageBusInterface $messageBus;

    /** @var MigrationEntity current configured migration */
    private MigrationEntity $migrationEntity;

    /** @var string common service prefix containing the migration id */
    private string $servicePrefix;

    /**
     * List of task and migrator services indexed by service ids
     * @var array<string, object>
     */
    private array $serviceMap = [];

    public function __construct(
        MigrationRegistry $migrationRegistry,
        EntityManagerInterface $entityManager,
        MessageBusInterface $messageBus
    ) {
        $this->migrationRegistry = $migrationRegistry;
        $this->entityManager = $entityManager;
        $this->messageBus = $messageBus;
    }

    public function __invoke(StartMigration $startMigration)
    {
        // Get migration service
        $migrationId = $startMigration->getMigrationId();
        $migration = $this->migrationRegistry->get($migrationId);
        $this->servicePrefix = sprintf('fregata.migration.%s', $migrationId);

        if (null === $migration) {
            throw new \LogicException(sprintf('Cannot start a "%s" migration as it does not exist.', $migrationId));
        }

        // Create migration entity
        $this->createMigrationEntity($migrationId);

        // Create Before Task entities
        foreach ($migration->getBeforeTasks() as $beforeTask) {
            $this->createTaskEntity($beforeTask, TaskEntity::TASK_BEFORE);
        }

        // Create Migrators entities
        foreach ($migration->getMigrators() as $migrator) {
            $this->createMigratorEntity($migrator);
        }

        // Create After Task entities
        foreach ($migration->getAfterTasks() as $afterTask) {
            $this->createTaskEntity($afterTask, TaskEntity::TASK_AFTER);
        }

        $this->entityManager->flush();

        // Dispatch before task messages if applicable
        if ($this->migrationEntity->getBeforeTasks()->count() > 0) {
            $this->dispatchBeforeTaskMessages();
            return;
        }

        // Dispatch independent migrator messages if there is no before task
        $this->dispatchMigratorMessages();
    }

    /**
     * Build part of a service ID by converting an object's class name to snake case
     */
    private function getServiceId(object $object): string
    {
        $objectClass = get_class($object);
        $serviceSuffix = new UnicodeString($objectClass);
        return $serviceSuffix->snake()->toString();
    }

    /**
     * Create the migration entity
     */
    private function createMigrationEntity(string $migrationId): void
    {
        $this->migrationEntity = (new MigrationEntity())
            ->setServiceId($migrationId)
            ->setStatus(MigrationEntity::STATUS_CREATED)
        ;

        $this->entityManager->persist($this->migrationEntity);
    }

    /**
     * Create a task entity based on task service and type
     */
    private function createTaskEntity(TaskInterface $task, int $type): void
    {
        // Check task type
        $serviceTypes = [
            TaskEntity::TASK_BEFORE => 'before',
            TaskEntity::TASK_AFTER  => 'after',
        ];

        $serviceType = $serviceTypes[$type] ?? null;
        if (null === $serviceType) {
            throw new \LogicException(sprintf('Unknown "%s" task type.', $type));
        }

        // Service ID
        $taskServiceId = sprintf('%s.task.%s.%s', $this->servicePrefix, $serviceType, $this->getServiceId($task));

        // Entity
        $taskEntity = (new TaskEntity())
            ->setServiceId($taskServiceId)
            ->setType($type)
            ->setStatus(TaskEntity::STATUS_CREATED)
        ;

        $this->migrationEntity->addTask($taskEntity);
        $this->entityManager->persist($taskEntity);
        $this->serviceMap[$taskServiceId] = $task;
    }

    /**
     * Create a migrator entity based on migrator service
     */
    private function createMigratorEntity(MigratorInterface $migrator): void
    {
        $migratorServiceId = sprintf('%s.migrator.%s', $this->servicePrefix, $this->getServiceId($migrator));

        $migratorEntity = (new MigratorEntity())
            ->setServiceId($migratorServiceId)
            ->setStatus(MigratorEntity::STATUS_CREATED)
        ;

        // Manage dependencies directly as migrators are already sorted
        if ($migrator instanceof DependentMigratorInterface) {
            foreach ($migrator->getDependencies() as $parentMigratorClass) {
                $parentMigratorEntity = $this->serviceMap[$parentMigratorClass];
                $parentMigratorEntity->addNextMigrator($migratorEntity);
            }
        }

        $this->migrationEntity->addMigrator($migratorEntity);
        $this->entityManager->persist($migratorEntity);
        $this->serviceMap[get_class($migrator)] = $migratorEntity;
    }

    /**
     * Dispatch user defined before task messages
     */
    private function dispatchBeforeTaskMessages(): void
    {
        // Filter core tasks
        $userTasks = $this->migrationEntity->getBeforeTasks()->filter(function (TaskEntity $task) {
            $taskService = $this->serviceMap[$task->getServiceId()];

            return ! ($taskService instanceof ForeignKeyBeforeTask);
        });

        // Run core tasks if there is no user defined tasks
        $tasks = $userTasks->count() > 0
            ? $userTasks
            : $this->migrationEntity->getBeforeTasks();

        foreach ($tasks as $taskEntity) {
            $this->messageBus->dispatch(new RunTask($taskEntity));
        }
    }

    /**
     * Dispatch independent migrator messages
     */
    private function dispatchMigratorMessages(): void
    {
        // Filter dependent migrators (with no "previous" migrators)
        $independentMigratorEntities = $this->migrationEntity->getMigrators()
            ->filter(fn(MigratorEntity $migratorEntity) => $migratorEntity->getPreviousMigrators()->count() === 0)
        ;

        foreach ($independentMigratorEntities as $migratorEntity) {
            $this->messageBus->dispatch(new RunMigrator($migratorEntity));
        }
    }
}
