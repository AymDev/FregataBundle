<?php

namespace Fregata\FregataBundle\Doctrine\Migrator;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @internal
 * @extends ServiceEntityRepository<MigratorEntity>
 */
class MigratorRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MigratorEntity::class);
    }
}
