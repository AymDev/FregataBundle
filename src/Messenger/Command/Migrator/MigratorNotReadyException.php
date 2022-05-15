<?php

namespace Fregata\FregataBundle\Messenger\Command\Migrator;

use Fregata\FregataBundle\Doctrine\Migrator\MigratorEntity;

class MigratorNotReadyException extends \Exception
{
    public function __construct(MigratorEntity $migratorEntity)
    {
        if (null === $migratorEntity->getMigration()) {
            throw new \LogicException('Migrator has no associated migration.');
        }

        parent::__construct(
            sprintf(
                'Migrator %d in %s status is not ready as the migration is in %s state',
                $migratorEntity->getId(),
                $migratorEntity->getStatus(),
                $migratorEntity->getMigration()->getStatus()
            ),
            1647108455929
        );
    }
}
