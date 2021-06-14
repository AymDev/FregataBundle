<?php

namespace Fregata\FregataBundle\Messenger\Command\Migrator;

use Fregata\FregataBundle\Doctrine\Migrator\MigratorEntity;
use Fregata\FregataBundle\Messenger\FregataMessageInterface;

/**
 * @internal
 * Execute a migrator
 */
class RunMigrator implements FregataMessageInterface
{
    private int $migratorId;

    public function __construct(MigratorEntity $migratorEntity)
    {
        $this->migratorId = $migratorEntity->getId();
    }

    public function getMigratorId(): int
    {
        return $this->migratorId;
    }
}
