<?php

namespace App\Fregata\Dependency\Task;

use Fregata\Migration\TaskInterface;

class FirstTask implements TaskInterface
{
    public function execute(): ?string
    {
        sleep(10);
        return 'Task OK';
    }
}
