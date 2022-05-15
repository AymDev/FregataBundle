<?php

namespace Tests\Fregata\FregataBundle\Messenger\Command\Task;

use Fregata\FregataBundle\Doctrine\Migration\MigrationEntity;
use Fregata\FregataBundle\Doctrine\Task\TaskEntity;
use Fregata\FregataBundle\Messenger\Command\Task\TaskNotReadyException;
use Tests\Fregata\FregataBundle\Messenger\AbstractMessengerTestCase;

class TaskNotReadyExceptionTest extends AbstractMessengerTestCase
{
    /**
     * The task must have a migration as its readiness depends on it.
     */
    public function testTaskMustHaveAMigration(): void
    {
        // Task creation
        $task = new TaskEntity();

        // Exception test
        self::expectException(\LogicException::class);
        new TaskNotReadyException($task);
    }

    /**
     * The exception message must contain important information
     */
    public function testExceptionMessageContent(): void
    {
        // Task creation
        $task = (new TaskEntity())
            ->setServiceId('task.id')
            ->setType(TaskEntity::TASK_BEFORE)
            ->setStatus(MigrationEntity::STATUS_MIGRATORS);

        $migration = (new MigrationEntity())
            ->setServiceId('migration.id')
            ->addTask($task)
            ->setStatus(TaskEntity::STATUS_CREATED);

        $this->getEntityManager()->persist($migration);
        $this->getEntityManager()->persist($task);
        $this->getEntityManager()->flush();

        // Exception test
        $exception = new TaskNotReadyException($task);
        $message = $exception->getMessage();

        self::assertStringContainsString((string)$task->getId(), $message);
        self::assertStringContainsString($task->getStatus(), $message);
        self::assertInstanceOf(MigrationEntity::class, $task->getMigration());
        self::assertStringContainsString($task->getMigration()->getStatus(), $message);
    }
}
