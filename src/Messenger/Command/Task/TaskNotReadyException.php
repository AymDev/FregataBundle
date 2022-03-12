<?php

namespace Fregata\FregataBundle\Messenger\Command\Task;

use Fregata\FregataBundle\Doctrine\Task\TaskEntity;

class TaskNotReadyException extends \Exception
{
    public function __construct(TaskEntity $taskEntity)
    {
        parent::__construct(
            sprintf(
                'Task %d in %s status is not ready as the migration is in %s state',
                $taskEntity->getId(),
                $taskEntity->getStatus(),
                $taskEntity->getMigration()->getStatus()
            ),
            1623880572586
        );
    }
}
