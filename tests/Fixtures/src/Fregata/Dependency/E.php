<?php

namespace App\Fregata\Dependency;

use App\Fregata\TestMigration\Migrator;
use Fregata\Migration\Migrator\DependentMigratorInterface;

class E extends Migrator implements DependentMigratorInterface
{
    public function getDependencies(): array
    {
        return [
            D::class,
            G::class,
        ];
    }
}
