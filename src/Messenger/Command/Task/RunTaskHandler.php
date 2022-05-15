<?php

namespace Fregata\FregataBundle\Messenger\Command\Task;

use Doctrine\ORM\EntityManagerInterface;
use Fregata\Adapter\Doctrine\DBAL\ForeignKey\Task\ForeignKeyAfterTask;
use Fregata\Adapter\Doctrine\DBAL\ForeignKey\Task\ForeignKeyBeforeTask;
use Fregata\FregataBundle\Doctrine\Migration\MigrationEntity;
use Fregata\FregataBundle\Doctrine\Task\TaskEntity;
use Fregata\FregataBundle\Doctrine\Task\TaskRepository;
use Fregata\FregataBundle\Messenger\Command\Migrator\RunMigrator;
use Fregata\Migration\TaskInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal
 * Execute a before or after task
 */
class RunTaskHandler implements MessageHandlerInterface
{
    private ServiceLocator $serviceLocator;
    private EntityManagerInterface $entityManager;
    private TaskRepository $taskRepository;
    private MessageBusInterface $messageBus;
    private LoggerInterface $logger;

    private TaskEntity $taskEntity;
    private MigrationEntity $migrationEntity;

    public function __construct(
        ServiceLocator $serviceLocator,
        EntityManagerInterface $entityManager,
        TaskRepository $taskRepository,
        MessageBusInterface $messageBus,
        LoggerInterface $logger
    ) {
        $this->serviceLocator = $serviceLocator;
        $this->entityManager = $entityManager;
        $this->taskRepository = $taskRepository;
        $this->messageBus = $messageBus;
        $this->logger = $logger;
    }

    public function __invoke(RunTask $runTask): void
    {
        // Find the task entity
        $taskEntity = $this->taskRepository->find($runTask->getTaskId());
        if (null === $taskEntity) {
            $this->failure('Unknown task ID.', [
                'id' => $runTask->getTaskId(),
            ]);
        }
        $this->taskEntity = $taskEntity;

        if (null === $this->taskEntity->getMigration()) {
            $this->failure('Task has no migration.', [
                'id' => $this->taskEntity->getId(),
            ]);
        }
        $this->migrationEntity = $this->taskEntity->getMigration();

        // Do not run task multiple times
        if (TaskEntity::STATUS_CREATED !== $this->taskEntity->getStatus()) {
            $this->logger->notice('Task already executed.', [
                'task' => $this->taskEntity->getId(),
            ]);
            return;
        }

        // Canceled/failed migration
        $cancelingStatuses = [MigrationEntity::STATUS_CANCELED, MigrationEntity::STATUS_FAILURE];
        if (in_array($this->migrationEntity->getStatus(), $cancelingStatuses, true)) {
            $this->taskEntity->setStatus(TaskEntity::STATUS_CANCELED);
            $this->entityManager->flush();
            $this->logger->notice('Canceled task.', [
                'task' => $this->taskEntity->getId(),
            ]);
            return;
        }

        // Task type check
        if (false === in_array($this->taskEntity->getType(), [TaskEntity::TASK_BEFORE, TaskEntity::TASK_AFTER], true)) {
            $this->failure('Unknown task type.', [
                'task' => $this->taskEntity->getId(),
                'type' => $this->taskEntity->getType(),
            ]);
        }

        // Update migration status
        try {
            $this->checkMigrationStatus();
        } catch (TaskNotReadyException $exception) {
            $this->logger->notice($exception->getMessage());
            return;
        }

        // Update task status
        $this->taskEntity
            ->setStatus(TaskEntity::STATUS_RUNNING)
            ->setStartedAt();
        $this->entityManager->flush();

        // Execute task
        try {
            $task = $this->getTaskService($this->taskEntity);
            $task->execute();
        } catch (\Throwable $error) {
            $this->failure('Task failed.', [
                'id' => $this->taskEntity->getId(),
                'error' => $error
            ]);
        }

        // Task succeeded
        $this->taskEntity
            ->setStatus(TaskEntity::STATUS_FINISHED)
            ->setFinishedAt();
        $this->entityManager->flush();

        // Dispatch next messages
        $this->dispatchNextMessages();
    }

    /**
     * Declares the current task as failed
     * @param mixed[] $context
     * @return no-return
     */
    private function failure(string $message, array $context = []): void
    {
        // Log a message
        $this->logger->critical($message, $context);

        // Set task and migration in the failure status
        if (isset($this->taskEntity)) {
            $this->taskEntity
                ->setStatus(TaskEntity::STATUS_FAILURE)
                ->setFinishedAt();
        }

        if (isset($this->migrationEntity)) {
            $this->migrationEntity
                ->setStatus(MigrationEntity::STATUS_FAILURE)
                ->setFinishedAt();
        }

        if (isset($this->taskEntity) || isset($this->migrationEntity)) {
            $this->entityManager->flush();
        }

        throw new \RuntimeException($message);
    }

    /**
     * Get the task service associated with a task entity
     */
    private function getTaskService(TaskEntity $taskEntity): TaskInterface
    {
        if (null === $taskEntity->getServiceId()) {
            $this->failure('Missing task service ID.', [
                'taskId' => $taskEntity->getId(),
            ]);
        }

        if (false === $this->serviceLocator->has($taskEntity->getServiceId())) {
            $this->failure('Unknown task service.', [
                'taskId' => $taskEntity->getId(),
                'serviceId' => $taskEntity->getServiceId(),
            ]);
        }

        /** @var TaskInterface $taskService */
        $taskService = $this->serviceLocator->get($taskEntity->getServiceId());
        return $taskService;
    }

    /**
     * Check if a task entity holds a core task service
     */
    private function isCore(TaskEntity $taskEntity): bool
    {
        $service = $this->getTaskService($taskEntity);

        return $service instanceof ForeignKeyBeforeTask
            || $service instanceof ForeignKeyAfterTask;
    }

    /**
     * Check the task and migration statuses to update the migration status or abort the task execution
     * @throws TaskNotReadyException
     */
    private function checkMigrationStatus(): void
    {
        // Behaviour is a bit different for before/after tasks
        $isBefore = TaskEntity::TASK_BEFORE === $this->taskEntity->getType();

        $validMigrationStatuses = [
            TaskEntity::TASK_BEFORE => [
                MigrationEntity::STATUS_CREATED,
                MigrationEntity::STATUS_BEFORE_TASKS,
                MigrationEntity::STATUS_CORE_BEFORE_TASKS
            ],
            TaskEntity::TASK_AFTER => [
                MigrationEntity::STATUS_MIGRATORS,
                MigrationEntity::STATUS_CORE_AFTER_TASKS,
                MigrationEntity::STATUS_AFTER_TASKS
            ],
        ];

        // Invalid migration status
        $validStatuses = $validMigrationStatuses[$this->taskEntity->getType()];
        if (false === in_array($this->migrationEntity->getStatus(), $validStatuses, true)) {
            $this->failure('Migration in invalid state to run task.', [
                'migration'        => $this->migrationEntity->getId(),
                'migration_status' => $this->migrationEntity->getStatus(),
                'task'             => $this->taskEntity->getId(),
                'task_status'      => $this->taskEntity->getStatus(),
            ]);
        }

        /*
         * Core before tasks must wait for user tasks to complete
         * User after tasks must wait for core tasks to complete
         */
        if ($isBefore === $this->isCore($this->taskEntity)) {
            $remainingTasks = $this->migrationEntity->getTasks()
                ->filter(function (TaskEntity $task) use ($isBefore) {
                    if ($task->getType() !== $this->taskEntity->getType() || $isBefore === $this->isCore($task)) {
                        return false;
                    }

                    return false === $task->hasEnded();
                });


            if ($remainingTasks->count() > 0) {
                // Task is not ready
                throw new TaskNotReadyException($this->taskEntity);
            }

            $this->updateMigrationStatus(
                $isBefore
                ? MigrationEntity::STATUS_CORE_BEFORE_TASKS
                : MigrationEntity::STATUS_AFTER_TASKS
            );
            return;
        }

        // The task does not have to wait
        $this->updateMigrationStatus(
            $isBefore
                ? MigrationEntity::STATUS_BEFORE_TASKS
                : MigrationEntity::STATUS_CORE_AFTER_TASKS
        );
    }

    /**
     * Update the migration status if needed
     */
    private function updateMigrationStatus(string $status): void
    {
        if ($status !== $this->migrationEntity->getStatus()) {
            // Set start time if not set already
            $this->migrationEntity->setStartedAt();

            $this->migrationEntity->setStatus($status);
            $this->logger->info(sprintf('Migration reached the %s status', $status), [
                'migration' => $this->migrationEntity->getId(),
            ]);
        }
    }

    /**
     * Dispatch messages if the migration is ready
     * Can also set the end of the migration if applicable
     */
    private function dispatchNextMessages(): void
    {
        // Behaviour is a bit different for before/after tasks
        $isBefore = TaskEntity::TASK_BEFORE === $this->taskEntity->getType();

        /*
         * User before tasks may trigger core tasks
         * Core after tasks may trigger user tasks
         */
        if ($isBefore !== $this->isCore($this->taskEntity)) {
            $remainingTasks = $this->migrationEntity->getTasks()
                ->filter(function (TaskEntity $task) use ($isBefore) {
                    return $task->getId() !== $this->taskEntity->getId()
                        && $task->getType() === $this->taskEntity->getType()
                        && $isBefore !== $this->isCore($task)
                        && false === $task->hasEnded();
                });

            // Some tasks of the same category as the current one are still running
            if ($remainingTasks->count() > 0) {
                return;
            }

            $nextTasks = $this->migrationEntity->getTasks()
                ->filter(function (TaskEntity $task) use ($isBefore) {
                    return $task->getType() === $this->taskEntity->getType()
                        && $isBefore === $this->isCore($task);
                });

            // Dispatch next task messages
            if ($nextTasks->count() > 0) {
                /** @var TaskEntity $task */
                foreach ($nextTasks as $task) {
                    /** @var int $taskId */
                    $taskId = $task->getId();
                    $this->messageBus->dispatch(new RunTask($taskId));
                }
                return;
            }

            $this->triggerNextStep();
            return;
        }

        // Other tasks
        $remainingTasks = $this->migrationEntity->getTasks()
            ->filter(function (TaskEntity $task) use ($isBefore) {
                return $task->getId() !== $this->taskEntity->getId()
                    && $task->getType() === $this->taskEntity->getType()
                    && $isBefore === $this->isCore($task)
                    && false === $task->hasEnded();
            });

        // Some tasks of the same category as the current one are still running
        if ($remainingTasks->count() > 0) {
            return;
        }

        $this->triggerNextStep();
    }

    /**
     * Dispatch the next step messages or ends the migration
     */
    private function triggerNextStep(): void
    {
        if (TaskEntity::TASK_BEFORE === $this->taskEntity->getType()) {
            // Dispatch migrator messages
            foreach ($this->migrationEntity->getMigrators() as $migratorEntity) {
                $this->messageBus->dispatch(new RunMigrator($migratorEntity));
            }
        } else {
            // End of the migration
            $this->updateMigrationStatus(MigrationEntity::STATUS_FINISHED);
            $this->migrationEntity->setFinishedAt();
            $this->entityManager->flush();
        }
    }
}
