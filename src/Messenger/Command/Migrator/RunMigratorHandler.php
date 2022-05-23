<?php

namespace Fregata\FregataBundle\Messenger\Command\Migrator;

use Doctrine\ORM\EntityManagerInterface;
use Fregata\FregataBundle\Doctrine\ComponentStatus;
use Fregata\FregataBundle\Doctrine\Migration\MigrationEntity;
use Fregata\FregataBundle\Doctrine\Migration\MigrationStatus;
use Fregata\FregataBundle\Doctrine\Migrator\MigratorEntity;
use Fregata\FregataBundle\Doctrine\Migrator\MigratorRepository;
use Fregata\FregataBundle\Doctrine\Task\TaskEntity;
use Fregata\FregataBundle\Messenger\Command\Task\RunTask;
use Fregata\Migration\Migrator\MigratorInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal
 * Execute a migrator
 */
class RunMigratorHandler implements MessageHandlerInterface
{
    private MigratorEntity $migratorEntity;
    private MigrationEntity $migrationEntity;

    public function __construct(
        private readonly ServiceLocator $serviceLocator,
        private readonly EntityManagerInterface $entityManager,
        private readonly MigratorRepository $migratorRepository,
        private readonly MessageBusInterface $messageBus,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(RunMigrator $runMigrator): void
    {
        // Find the migrator entity
        $migratorEntity = $this->migratorRepository->find($runMigrator->getMigratorId());
        if (null === $migratorEntity) {
            $this->failure('Unknown migrator ID.', [
                'id' => $runMigrator->getMigratorId(),
            ]);
        }
        $this->migratorEntity = $migratorEntity;

        if (null === $this->migratorEntity->getMigration()) {
            $this->failure('Migrator has no migration.', [
                'id' => $this->migratorEntity->getId(),
            ]);
        }
        $this->migrationEntity = $this->migratorEntity->getMigration();

        // Do not run migrator multiple times
        if (ComponentStatus::CREATED !== $this->migratorEntity->getStatus()) {
            $this->logger->notice('Migrator already executed.', [
                'migrator' => $this->migratorEntity->getId(),
            ]);
            return;
        }

        // Canceled/failed migration
        $cancelingStatuses = [MigrationStatus::CANCELED, MigrationStatus::FAILURE];
        if (in_array($this->migrationEntity->getStatus(), $cancelingStatuses, true)) {
            $this->migratorEntity->setStatus(ComponentStatus::CANCELED);
            $this->entityManager->flush();
            $this->logger->notice('Canceled migrator.', [
                'migrator' => $this->migratorEntity->getId(),
            ]);
            return;
        }

        // Update migration status
        try {
            $this->checkMigrationStatus();
        } catch (MigratorNotReadyException $exception) {
            $this->logger->notice($exception->getMessage());
            return;
        }

        // Update task status
        $this->migratorEntity
            ->setStatus(ComponentStatus::RUNNING)
            ->setStartedAt();
        $this->entityManager->flush();

        // Run migrator
        try {
            $migrator = $this->getMigratorService($this->migratorEntity);
            $totalPushCount = 0;

            $puller = $migrator->getPuller();
            $pusher = $migrator->getPusher();
            foreach ($migrator->getExecutor()->execute($puller, $pusher) as $pushedItemCount) {
                $totalPushCount += $pushedItemCount;
            }
        } catch (\Throwable $error) {
            $this->failure('Migrator failed.', [
                'id' => $this->migratorEntity->getId(),
                'error' => $error
            ]);
        }

        // Migrator succeeded
        $this->migratorEntity
            ->setStatus(ComponentStatus::FINISHED)
            ->setFinishedAt();
        $this->entityManager->flush();

        // Dispatch next messages
        $this->dispatchNextMessages();
    }

    /**
     * Declares the current migrator as failed
     * @param mixed[] $context
     * @return no-return
     */
    private function failure(string $message, array $context = []): void
    {
        // Log a message
        $this->logger->critical($message, $context);

        // Set task and migration in the failure status
        if (isset($this->migratorEntity)) {
            $this->migratorEntity
                ->setStatus(ComponentStatus::FAILURE)
                ->setFinishedAt();
        }

        if (isset($this->migrationEntity)) {
            $this->migrationEntity
                ->setStatus(MigrationStatus::FAILURE)
                ->setFinishedAt();
        }

        if (isset($this->migratorEntity) || isset($this->migrationEntity)) {
            $this->entityManager->flush();
        }

        throw new \RuntimeException($message);
    }

    /**
     * Get the migrator service associated with a migrator entity
     */
    private function getMigratorService(MigratorEntity $migratorEntity): MigratorInterface
    {
        if (null === $migratorEntity->getServiceId()) {
            $this->failure('Missing task service ID.', [
                'taskId' => $migratorEntity->getId(),
            ]);
        }

        if (false === $this->serviceLocator->has($migratorEntity->getServiceId())) {
            $this->failure('Unknown migrator service.', [
                'migratorId' => $migratorEntity->getId(),
                'serviceId' => $migratorEntity->getServiceId(),
            ]);
        }

        /** @var MigratorInterface $migratorService */
        $migratorService = $this->serviceLocator->get($migratorEntity->getServiceId());
        return $migratorService;
    }

    /**
     * Check the migrator and migration statuses to update the migration status or abort the migrator execution
     * @throws MigratorNotReadyException
     */
    private function checkMigrationStatus(): void
    {
        $validMigrationStatuses = [
            MigrationStatus::CREATED,
            MigrationStatus::BEFORE_TASKS,
            MigrationStatus::CORE_BEFORE_TASKS,
            MigrationStatus::MIGRATORS,
        ];

        // Invalid migration status
        if (false === in_array($this->migrationEntity->getStatus(), $validMigrationStatuses, true)) {
            $this->failure('Migration in invalid state to run task.', [
                'migration'        => $this->migrationEntity->getId(),
                'migration_status' => $this->migrationEntity->getStatus(),
                'migrator'         => $this->migratorEntity->getId(),
                'migrator_status'  => $this->migratorEntity->getStatus(),
            ]);
        }

        // Migrators must wait for before tasks to complete
        if (MigrationStatus::MIGRATORS !== $this->migrationEntity->getStatus()) {
            $remainingTasks = $this->migrationEntity->getBeforeTasks()
                ->filter(fn(TaskEntity $task) => false === $task->hasEnded());

            if ($remainingTasks->count() > 0) {
                // Migrator is not ready
                throw new MigratorNotReadyException($this->migratorEntity);
            }
        }

        // Dependent migrators must wait for the previous ones to complete
        $remainingMigrators = $this->migratorEntity->getPreviousMigrators()
            ->filter(fn(MigratorEntity $migrator) => false === $migrator->hasEnded());

        if ($remainingMigrators->count() > 0) {
            // Migrator is not ready
            throw new MigratorNotReadyException($this->migratorEntity);
        }

        $this->updateMigrationStatus(MigrationStatus::MIGRATORS);
    }

    /**
     * Update the migration status if needed
     */
    private function updateMigrationStatus(MigrationStatus $status): void
    {
        if ($status !== $this->migrationEntity->getStatus()) {
            // Set start time if not set already
            $this->migrationEntity->setStartedAt();

            $this->migrationEntity->setStatus($status);
            $this->logger->info(sprintf('Migration reached the %s status', $status->value), [
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
        // Dispatch messages for the dependent migrators
        $nextMigrators = $this->migratorEntity->getNextMigrators()
            ->filter(fn(MigratorEntity $migrator) => ComponentStatus::CREATED === $migrator->getStatus());

        if ($nextMigrators->count() > 0) {
            foreach ($nextMigrators as $nextMigrator) {
                $this->messageBus->dispatch(new RunMigrator($nextMigrator));
            }
            return;
        }

        // Check remaining migrators before dispatching after task messages
        $remainingMigrators = $this->migrationEntity->getMigrators()
            ->filter(fn(MigratorEntity $migrator) => false === $migrator->hasEnded());

        if ($remainingMigrators->count() > 0) {
            return;
        }

        // Check for after tasks
        if ($this->migrationEntity->getAfterTasks()->count() > 0) {
            foreach ($this->migrationEntity->getAfterTasks() as $afterTask) {
                /** @var int $taskId */
                $taskId = $afterTask->getId();
                $this->messageBus->dispatch(new RunTask($taskId));
            }
            return;
        }


        // End of the migration
        $this->updateMigrationStatus(MigrationStatus::FINISHED);
        $this->migrationEntity->setFinishedAt();
        $this->entityManager->flush();
    }
}
