<?php

namespace Fregata\FregataBundle\Doctrine\Migration;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @internal
 * @extends ServiceEntityRepository<MigrationEntity>
 */
class MigrationRepository extends ServiceEntityRepository
{
    public const PAGINATION_OFFSET = 10;

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
        /** @var MigrationEntity[] $runningMigrations */
        $runningMigrations = $this->createQueryBuilder('m')
            ->where('m.status NOT IN(:statuses)')
            ->setParameter('statuses', [
                MigrationEntity::STATUS_CANCELED,
                MigrationEntity::STATUS_FAILURE,
                MigrationEntity::STATUS_FINISHED,
            ])
            ->orderBy('m.startedAt', 'DESC')
            ->getQuery()
            ->getResult();

        return new ArrayCollection($runningMigrations);
    }

    /**
     * Find the last run migration
     */
    public function getLast(): ?MigrationEntity
    {
        /** @var null|MigrationEntity $lastMigration */
        $lastMigration = $this->createQueryBuilder('m')
            ->orderBy('m.finishedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $lastMigration;
    }

    private function createHistoryQuery(int $page): QueryBuilder
    {
        return $this->createQueryBuilder('m')
            ->orderBy('m.finishedAt', 'DESC')
            ->addOrderBy('m.startedAt', 'DESC')
            ->addOrderBy('m.id', 'DESC')
            ->setFirstResult(($page - 1) * self::PAGINATION_OFFSET)
            ->setMaxResults(self::PAGINATION_OFFSET);
    }

    /**
     * Get a run history page
     * @return Paginator<MigrationEntity>
     */
    public function getPage(int $page): Paginator
    {
        return new Paginator($this->createHistoryQuery($page));
    }

    /**
     * Get a run history page for a specific migration
     * @return Paginator<MigrationEntity>
     */
    public function getPageForService(string $serviceId, int $page): Paginator
    {
        $query = $this->createHistoryQuery($page)
            ->where('m.serviceId = :serviceId')
            ->setParameter('serviceId', $serviceId);

        return new Paginator($query);
    }
}
