<?php

namespace Fregata\FregataBundle\Doctrine\Migration;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Fregata\FregataBundle\Doctrine\Migrator\MigratorEntity;
use Fregata\FregataBundle\Doctrine\Task\TaskEntity;

/**
 * @internal
 * @ORM\Entity(repositoryClass="Fregata\FregataBundle\Doctrine\Migration\MigrationRepository")
 * @ORM\Table(name="fregata_migration")
 */
class MigrationEntity
{
    public const STATUS_CREATED           = 0;
    public const STATUS_BEFORE_TASKS      = 1;
    public const STATUS_CORE_BEFORE_TASKS = 2;
    public const STATUS_MIGRATORS         = 3;
    public const STATUS_CORE_AFTER_TASKS  = 4;
    public const STATUS_AFTER_TASKS       = 5;
    public const STATUS_FINISHED          = 6;
    public const STATUS_FAILURE           = 7;
    public const STATUS_CANCELED          = 8;

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
     * @ORM\OneToMany(targetEntity="Fregata\FregataBundle\Doctrine\Task\TaskEntity", mappedBy="migration", orphanRemoval=true)
     * @var Collection<int, TaskEntity>
     */
    private Collection $tasks;

    /**
     * @ORM\OneToMany(targetEntity="Fregata\FregataBundle\Doctrine\Migrator\MigratorEntity", mappedBy="migration", orphanRemoval=true)
     * @var Collection<int, MigratorEntity>
     */
    private Collection $migrators;

    public function __construct()
    {
        $this->tasks = new ArrayCollection();
        $this->migrators = new ArrayCollection();
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

    /**
     * @return Collection<int, TaskEntity>
     */
    public function getTasks()
    {
        return $this->tasks;
    }

    public function addTask(TaskEntity $task): self
    {
        if (!$this->tasks->contains($task)) {
            $this->tasks[] = $task;
            $task->setMigration($this);
        }
        return $this;
    }

    public function removeTask(TaskEntity $task): self
    {
        if ($this->tasks->contains($task)) {
            $this->tasks->removeElement($task);
            $task->setMigration(null);
        }
        return $this;
    }

    /**
     * @return Collection<int, MigratorEntity>
     */
    public function getMigrators()
    {
        return $this->migrators;
    }

    public function addMigrator(MigratorEntity $migrator): self
    {
        if (!$this->migrators->contains($migrator)) {
            $this->migrators[] = $migrator;
            $migrator->setMigration($this);
        }
        return $this;
    }

    public function removeMigrator(MigratorEntity $migrator): self
    {
        if ($this->migrators->contains($migrator)) {
            $this->migrators->removeElement($migrator);
            $migrator->setMigration(null);
        }
        return $this;
    }
}
