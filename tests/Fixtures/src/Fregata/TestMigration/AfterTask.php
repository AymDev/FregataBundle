<?php

namespace App\Fregata\TestMigration;

use Fregata\Migration\TaskInterface;

class AfterTask implements TaskInterface
{
    public function execute(): ?string
    {
        sleep(10);
        return 'After Task OK';
    }
}
