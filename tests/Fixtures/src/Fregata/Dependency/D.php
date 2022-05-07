<?php

namespace App\Fregata\Dependency;

use App\Fregata\TestMigration\Migrator;
use Fregata\Migration\Migrator\DependentMigratorInterface;

class D extends Migrator implements DependentMigratorInterface
{
    public function getDependencies(): array
    {
        return [
            C::class,
            F::class,
        ];
    }
}
