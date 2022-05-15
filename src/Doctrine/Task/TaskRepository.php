<?php

namespace Fregata\FregataBundle\Doctrine\Task;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @internal
 * @extends ServiceEntityRepository<TaskEntity>
 */
class TaskRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TaskEntity::class);
    }
}
