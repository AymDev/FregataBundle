<?php

namespace Fregata\FregataBundle\Messenger\Command\Task;

use Fregata\FregataBundle\Doctrine\Task\TaskEntity;
use Fregata\FregataBundle\Messenger\FregataMessageInterface;

/**
 * @internal
 * Execute a before or after task
 */
class RunTask implements FregataMessageInterface
{
    private int $taskId;

    public function __construct(int $taskId)
    {
        $this->taskId = $taskId;
    }

    public function getTaskId(): int
    {
        return $this->taskId;
    }
}
