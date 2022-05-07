<?php

namespace App\Fregata\Dependency;

use App\Fregata\TestMigration\Migrator;
use Fregata\Migration\Migrator\DependentMigratorInterface;

class C extends Migrator implements DependentMigratorInterface
{
    public function getDependencies(): array
    {
        return [
            B::class,
        ];
    }
}
