<?php

namespace Tests\Fregata\FregataBundle\Messenger\Command\Task;

use Fregata\FregataBundle\Doctrine\Migration\MigrationEntity;
use Fregata\FregataBundle\Doctrine\Task\TaskEntity;
use Fregata\FregataBundle\Messenger\Command\Task\TaskNotReadyException;
use Tests\Fregata\FregataBundle\Messenger\AbstractMessengerTestCase;

class TaskNotReadyExceptionTest extends AbstractMessengerTestCase
{
    /**
     * The exception message must contain important information
     */
    public function testExceptionMessageContent()
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

        self::assertStringContainsString($task->getId(), $message);
        self::assertStringContainsString($task->getStatus(), $message);
        self::assertStringContainsString($task->getMigration()->getStatus(), $message);
    }
}
