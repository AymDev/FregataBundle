<?php

namespace Fregata\FregataBundle\Doctrine\Migrator;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Fregata\FregataBundle\Doctrine\Migration\MigrationEntity;

/**
 * @internal
 * @ORM\Entity(repositoryClass="Fregata\FregataBundle\Doctrine\Migrator\MigratorRepository")
 * @ORM\Table(name="fregata_migrator")
 */
class MigratorEntity
{
    public const STATUS_CREATED  = 0;
    public const STATUS_RUNNING  = 1;
    public const STATUS_FINISHED = 2;
    public const STATUS_FAILURE  = 3;
    public const STATUS_CANCELED = 4;

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private ?int $id = null;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private ?\DateTime $startedAt = null;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private ?\DateTime $finishedAt = null;

    /**
     * @ORM\Column(type="integer")
     */
    private int $status = self::STATUS_CREATED;

    /**
     * @ORM\Column(type="text")
     */
    private ?string $serviceId = null;

    /**
     * @ORM\ManyToOne(targetEntity="Fregata\FregataBundle\Doctrine\Migration\MigrationEntity", inversedBy="tasks")
     * @ORM\JoinColumn(nullable=false)
     */
    private ?MigrationEntity $migration = null;

    /**
     * @ORM\ManyToMany(targetEntity="Fregata\FregataBundle\Doctrine\Migrator\MigratorEntity", inversedBy="previousMigrators")
     * @var Collection<int, MigratorEntity>
     */
    private Collection $nextMigrators;

    /**
     * @ORM\ManyToMany(targetEntity="Fregata\FregataBundle\Doctrine\Migrator\MigratorEntity", mappedBy="nextMigrators")
     * @var Collection<int, MigratorEntity>
     */
    private Collection $previousMigrators;

    public function __construct()
    {
        $this->nextMigrators = new ArrayCollection();
        $this->previousMigrators = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStartedAt(): ?\DateTime
    {
        return $this->startedAt;
    }

    public function setStartedAt(\DateTime $startedAt): self
    {
        $this->startedAt = $startedAt;
        return $this;
    }

    public function getFinishedAt(): ?\DateTime
    {
        return $this->finishedAt;
    }

    public function setFinishedAt(\DateTime $finishedAt): self
    {
        $this->finishedAt = $finishedAt;
        return $this;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function setStatus(int $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getServiceId(): ?string
    {
        return $this->serviceId;
    }

    public function setServiceId(string $serviceId): self
    {
        $this->serviceId = $serviceId;
        return $this;
    }

    public function getMigration(): ?MigrationEntity
    {
        return $this->migration;
    }

    public function setMigration(?MigrationEntity $migration): self
    {
        $this->migration = $migration;
        return $this;
    }

    /**
     * @return Collection<int, MigratorEntity>
     */
    public function getNextMigrators(): Collection
    {
        return $this->nextMigrators;
    }

    public function addNextMigrator(self $migrator): self
    {
        if (!$this->nextMigrators->contains($migrator)) {
            $this->nextMigrators[] = $migrator;
            $migrator->addPreviousMigrator($this);
        }
        return $this;
    }

    public function removeNextMigrator(self $migrator): self
    {
        if ($this->nextMigrators->contains($migrator)) {
            $this->nextMigrators->removeElement($migrator);
            $migrator->removePreviousMigrator($this);
        }
        return $this;
    }

    /**
     * @return Collection<int, MigratorEntity>
     */
    public function getPreviousMigrators(): Collection
    {
        return $this->previousMigrators;
    }

    public function addPreviousMigrator(self $migrator): self
    {
        if (!$this->previousMigrators->contains($migrator)) {
            $this->previousMigrators[] = $migrator;
        }
        return $this;
    }

    public function removePreviousMigrator(self $migrator): self
    {
        if ($this->previousMigrators->contains($migrator)) {
            $this->previousMigrators->removeElement($migrator);
        }
        return $this;
    }
}
