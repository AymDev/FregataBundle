<?php

namespace App\Fregata\TestMigration;

use Fregata\Migration\TaskInterface;

class BeforeTask implements TaskInterface
{
    public function execute(): ?string
    {
        sleep(10);
        return 'Before Task OK';
    }
}
