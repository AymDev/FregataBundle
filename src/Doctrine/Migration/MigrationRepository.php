<?php

namespace Fregata\FregataBundle\Doctrine\Migration;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @internal
 */
class MigrationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MigrationEntity::class);
    }

    /**
     * Find running migrations
     * @return Collection<int, MigrationEntity>
     */
    public function getRunning(): Collection
    {
        return new ArrayCollection(
            $this->createQueryBuilder('m')
                ->where('m.status NOT IN(:statuses)')
                ->setParameter('statuses', [
                    MigrationEntity::STATUS_CANCELED,
                    MigrationEntity::STATUS_FAILURE,
                    MigrationEntity::STATUS_FINISHED,
                ])
                ->orderBy('m.startedAt', 'DESC')
                ->getQuery()
                ->getResult()
        );
    }

    /**
     * Find the last run migration
     */
    public function getLast(): ?MigrationEntity
    {
        return $this->createQueryBuilder('m')
            ->orderBy('m.finishedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
