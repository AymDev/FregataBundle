<?php

namespace Fregata\FregataBundle\Messenger\Command\Migrator;

use Doctrine\ORM\EntityManagerInterface;
use Fregata\FregataBundle\Doctrine\Migration\MigrationEntity;
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
    private ServiceLocator $serviceLocator;
    private EntityManagerInterface $entityManager;
    private MigratorRepository $migratorRepository;
    private MessageBusInterface $messageBus;
    private LoggerInterface $logger;

    /** @var MigratorEntity|null current task if found */
    private ?MigratorEntity $migratorEntity = null;

    public function __construct(
        ServiceLocator $serviceLocator,
        EntityManagerInterface $entityManager,
        MigratorRepository $migratorRepository,
        MessageBusInterface $messageBus,
        LoggerInterface $logger
    ) {
        $this->serviceLocator = $serviceLocator;
        $this->entityManager = $entityManager;
        $this->migratorRepository = $migratorRepository;
        $this->messageBus = $messageBus;
        $this->logger = $logger;
    }

    public function __invoke(RunMigrator $runMigrator)
    {
        // Find the migrator entity
        $this->migratorEntity = $this->migratorRepository->find($runMigrator->getMigratorId());
        if (null === $this->migratorEntity) {
            $this->failure('Unknown migrator ID.', [
                'id' => $runMigrator->getMigratorId(),
            ]);
        }

        // Canceled/failed migration
        if (in_array($this->migratorEntity->getMigration()->getStatus(), [MigrationEntity::STATUS_CANCELED, MigrationEntity::STATUS_FAILURE])) {
            $this->migratorEntity->setStatus(MigratorEntity::STATUS_CANCELED);
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
            ->setStatus(MigratorEntity::STATUS_RUNNING)
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
            ->setStatus(MigratorEntity::STATUS_FINISHED)
            ->setFinishedAt();
        $this->entityManager->flush();

        // Dispatch next messages
        $this->dispatchNextMessages();
    }

    /**
     * Declares the current migrator as failed
     * @return no-return
     */
    private function failure(string $message, array $context = []): void
    {
        // Log a message
        $this->logger->critical($message, $context);

        // Set task and migration in the failure status
        if (null !== $this->migratorEntity) {
            $this->migratorEntity
                ->setStatus(MigratorEntity::STATUS_FAILURE)
                ->setFinishedAt();

            $this->migratorEntity->getMigration()
                ->setStatus(MigrationEntity::STATUS_FAILURE)
                ->setFinishedAt();

            $this->entityManager->flush();
        }

        throw new \RuntimeException($message);
    }

    /**
     * Get the migrator service associated with a migrator entity
     */
    private function getMigratorService(MigratorEntity $migratorEntity): MigratorInterface
    {
        if (false === $this->serviceLocator->has($migratorEntity->getServiceId())) {
            $this->failure('Unknown migrator service.', [
                'migratorId' => $migratorEntity->getId(),
                'serviceId' => $migratorEntity->getServiceId(),
            ]);
        }

        return $this->serviceLocator->get($migratorEntity->getServiceId());
    }

    /**
     * Check the migrator and migration statuses to update the migration status or abort the migrator execution
     * @throws MigratorNotReadyException
     */
    private function checkMigrationStatus(): void
    {
        $validMigrationStatuses = [
            MigrationEntity::STATUS_CREATED,
            MigrationEntity::STATUS_BEFORE_TASKS,
            MigrationEntity::STATUS_CORE_BEFORE_TASKS,
            MigrationEntity::STATUS_MIGRATORS,
        ];

        // Invalid migration status
        if (false === in_array($this->migratorEntity->getMigration()->getStatus(), $validMigrationStatuses)) {
            $this->failure('Migration in invalid state to run task.', [
                'migration'        => $this->migratorEntity->getMigration()->getId(),
                'migration_status' => $this->migratorEntity->getMigration()->getStatus(),
                'migrator'         => $this->migratorEntity->getId(),
                'migrator_status'  => $this->migratorEntity->getStatus(),
            ]);
        }

        // Migrators must wait for before tasks to complete
        if (MigrationEntity::STATUS_MIGRATORS !== $this->migratorEntity->getMigration()->getStatus()) {
            $remainingTasks = $this->migratorEntity->getMigration()->getBeforeTasks()
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

        $this->updateMigrationStatus(MigrationEntity::STATUS_MIGRATORS);
    }

    /**
     * Update the migration status if needed
     */
    private function updateMigrationStatus(string $status): void
    {
        if ($status !== $this->migratorEntity->getMigration()->getStatus()) {
            // Set start time if not set already
            $this->migratorEntity->getMigration()->setStartedAt();

            $this->migratorEntity->getMigration()->setStatus($status);
            $this->logger->info(sprintf('Migration reached the %s status', $status), [
                'migration' => $this->migratorEntity->getMigration()->getId(),
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
        if ($this->migratorEntity->getNextMigrators()->count() > 0) {
            foreach ($this->migratorEntity->getNextMigrators() as $nextMigrator) {
                $this->messageBus->dispatch(new RunMigrator($nextMigrator));
            }
            return;
        }

        // Check remaining migrators before dispatching after task messages
        $remainingMigrators = $this->migratorEntity->getMigration()->getMigrators()
            ->filter(fn(MigratorEntity $migrator) => false === $migrator->hasEnded());

        if ($remainingMigrators->count() > 0) {
            return;
        }

        // Check for after tasks
        if ($this->migratorEntity->getMigration()->getAfterTasks()->count() > 0) {
            foreach ($this->migratorEntity->getMigration()->getAfterTasks() as $afterTask) {
                $this->messageBus->dispatch(new RunTask($afterTask->getId()));
            }
            return;
        }


        // End of the migration
        $this->updateMigrationStatus(MigrationEntity::STATUS_FINISHED);
        $this->migratorEntity->getMigration()->setFinishedAt();
        $this->entityManager->flush();
    }
}
