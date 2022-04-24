<?php

namespace Fregata\FregataBundle\Controller;

use Fregata\FregataBundle\Doctrine\Migration\MigrationEntity;
use Fregata\FregataBundle\Doctrine\Migrator\MigratorEntity;

/**
 * @internal
 */
final class MigratorSorter
{
    /**
     * Split and sort migrators by their dependencies
     * @return MigratorEntity[][]
     */
    public function sort(MigrationEntity $migration): array
    {
        /** @var MigratorEntity[][] $groups */
        $groups = [];

        // Build initial groups
        foreach ($migration->getMigrators() as $migrator) {
            if ($migrator->getPreviousMigrators()->count() === 0) {
                $this->registerMigrator($groups, 0, $migrator);
            }
        }

        // Filter duplicated nodes
        $knownServices = [];
        for ($i = count($groups) - 1; $i >= 0; $i--) {
            for ($j = 0; $j < count($groups[$i]); $j++) {
                if (in_array($groups[$i][$j]->getServiceId(), $knownServices, true)) {
                    array_splice($groups[$i], $j, 1);
                    $j--;
                } else {
                    $knownServices[] = $groups[$i][$j]->getServiceId();
                }
            }
        }

        return $groups;
    }

    private function registerMigrator(&$groups, int $level, MigratorEntity $migrator): void
    {
        $groups[$level][] = $migrator;

        foreach ($migrator->getNextMigrators() as $nextMigrator) {
            $this->registerMigrator($groups, $level + 1, $nextMigrator);
        }
    }
}
