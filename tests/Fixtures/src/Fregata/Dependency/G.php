<?php

namespace App\Fregata\Dependency;

use App\Fregata\TestMigration\Migrator;
use Fregata\Migration\Migrator\DependentMigratorInterface;

class G extends Migrator implements DependentMigratorInterface
{
    public function getDependencies(): array
    {
        return [
            B::class,
            C::class,
        ];
    }
}
