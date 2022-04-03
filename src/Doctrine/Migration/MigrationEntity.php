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
    public const STATUS_CREATED           = 'CREATED';
    public const STATUS_BEFORE_TASKS      = 'BEFORE_TASKS';
    public const STATUS_CORE_BEFORE_TASKS = 'CORE_BEFORE_TASKS';
    public const STATUS_MIGRATORS         = 'MIGRATORS';
    public const STATUS_CORE_AFTER_TASKS  = 'CORE_AFTER_TASKS';
    public const STATUS_AFTER_TASKS       = 'AFTER_TASKS';
    public const STATUS_FINISHED          = 'FINISHED';
    public const STATUS_FAILURE           = 'FAILURE';
    public const STATUS_CANCELED          = 'CANCELED';

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
     * @ORM\Column(type="string", length=50)
     */
    private string $status = self::STATUS_CREATED;

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

    public function setStartedAt(): self
    {
        if (null === $this->startedAt) {
            $this->startedAt = new \DateTime();
        }
        return $this;
    }

    public function getFinishedAt(): ?\DateTime
    {
        return $this->finishedAt;
    }

    public function setFinishedAt(): self
    {
        if (null === $this->finishedAt) {
            $this->finishedAt = new \DateTime();
        }
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function hasEnded(): bool
    {
        return in_array($this->getStatus(), [
            self::STATUS_FINISHED,
            self::STATUS_FAILURE,
            self::STATUS_CANCELED,
        ]);
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

    /**
     * @return Collection<int, TaskEntity>
     */
    public function getBeforeTasks()
    {
        return $this->tasks->filter(fn(TaskEntity $task) => $task->getType() === TaskEntity::TASK_BEFORE);
    }

    /**
     * @return Collection<int, TaskEntity>
     */
    public function getAfterTasks()
    {
        return $this->tasks->filter(fn(TaskEntity $task) => $task->getType() === TaskEntity::TASK_AFTER);
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
     * @return Collection<int, MigratorEntity>|MigratorEntity[]
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
