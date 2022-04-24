<?php

namespace Tests\Fregata\FregataBundle\Controller;

use Fregata\FregataBundle\Controller\MigratorSorter;
use Fregata\FregataBundle\Doctrine\Migration\MigrationEntity;
use Fregata\FregataBundle\Doctrine\Migrator\MigratorEntity;
use PHPUnit\Framework\TestCase;

class MigratorSorterTest extends TestCase
{
    /**
     * @param MigratorEntity[][] $groups
     * @param string[][] $expectedStructure
     */
    private function assertMigratorGroupStructure(array $groups, array $expectedStructure): void
    {
        $structure = array_map(
            fn(array $group) => array_map(
                fn(MigratorEntity $migrator) => $migrator->getServiceId(),
                $group
            ),
            $groups
        );

        self::assertSame($expectedStructure, $structure);
    }

    /**
     * Basic sorting works: A --- B --- C
     */
    public function testDependentMigratorsAreSorted(): void
    {
        // Setup dependencies
        $a = (new MigratorEntity())
            ->setServiceId('a');

        $b = (new MigratorEntity())
            ->setServiceId('b')
            ->addPreviousMigrator($a);

        $c = (new MigratorEntity())
            ->setServiceId('c')
            ->addPreviousMigrator($b);

        $migration = (new MigrationEntity())
            ->addMigrator($a)
            ->addMigrator($b)
            ->addMigrator($c);

        // Test
        $sorter = new MigratorSorter();
        $groups = $sorter->sort($migration);

        $this->assertMigratorGroupStructure($groups, [
            ['a'],
            ['b'],
            ['c'],
        ]);
    }

    /**
     * Independent migrators are in first group:
     *      A --- B
     *      C
     */
    public function testIndependentMigratorsAreInFirstGroup(): void
    {
        // Setup dependencies
        $a = (new MigratorEntity())
            ->setServiceId('a');

        $b = (new MigratorEntity())
            ->setServiceId('b')
            ->addPreviousMigrator($a);

        $c = (new MigratorEntity())
            ->setServiceId('c');

        $migration = (new MigrationEntity())
            ->addMigrator($a)
            ->addMigrator($b)
            ->addMigrator($c);

        // Test
        $sorter = new MigratorSorter();
        $groups = $sorter->sort($migration);

        $this->assertMigratorGroupStructure($groups, [
            ['a', 'c'],
            ['b'],
        ]);
    }

    /**
     * Interdependent migrators are not duplicated:
     *             /-----/--- G ---\
     *      A --- B --- C --- D --- E
     *       \--- F ---------/
     */
    public function testInterdependentMigratorsAreNotDuplicated(): void
    {
        // Setup dependencies
        $a = (new MigratorEntity())
            ->setServiceId('a');

        $b = (new MigratorEntity())
            ->setServiceId('b')
            ->addPreviousMigrator($a);

        $c = (new MigratorEntity())
            ->setServiceId('c')
            ->addPreviousMigrator($b);

        $d = (new MigratorEntity())
            ->setServiceId('d')
            ->addPreviousMigrator($c);

        $e = (new MigratorEntity())
            ->setServiceId('e')
            ->addPreviousMigrator($d);

        $f = (new MigratorEntity())
            ->setServiceId('f')
            ->addPreviousMigrator($a)
            ->addNextMigrator($d);

        $g = (new MigratorEntity())
            ->setServiceId('g')
            ->addPreviousMigrator($b)
            ->addPreviousMigrator($c)
            ->addNextMigrator($e);

        $migration = (new MigrationEntity())
            ->addMigrator($a)
            ->addMigrator($b)
            ->addMigrator($c)
            ->addMigrator($d)
            ->addMigrator($e)
            ->addMigrator($f)
            ->addMigrator($g);

        // Test
        $sorter = new MigratorSorter();
        $groups = $sorter->sort($migration);

        $this->assertMigratorGroupStructure($groups, [
            ['a'],
            ['b', 'f'],
            ['c'],
            ['d', 'g'],
            ['e'],
        ]);
    }
}
