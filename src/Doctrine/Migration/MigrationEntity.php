<?php

namespace Fregata\FregataBundle\Doctrine\Migration;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Fregata\FregataBundle\Doctrine\FregataComponentInterface;
use Fregata\FregataBundle\Doctrine\Migrator\MigratorEntity;
use Fregata\FregataBundle\Doctrine\Task\TaskEntity;
use Fregata\FregataBundle\Doctrine\Task\TaskType;

/**
 * @internal
 */
#[ORM\Entity(repositoryClass: MigrationRepository::class)]
#[ORM\Table(name: 'fregata_migration')]
class MigrationEntity implements FregataComponentInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTime $startedAt = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTime $finishedAt = null;

    #[ORM\Column(type: 'string', enumType: MigrationStatus::class)]
    private MigrationStatus $status = MigrationStatus::CREATED;

    #[ORM\Column(type: 'text')]
    private ?string $serviceId = null;

    /** @var Collection<int, TaskEntity> */
    #[ORM\OneToMany(mappedBy: 'migration', targetEntity: TaskEntity::class, orphanRemoval: true)]
    private Collection $tasks;

    /** @var Collection<int, MigratorEntity> */
    #[ORM\OneToMany(mappedBy: 'migration', targetEntity: MigratorEntity::class, orphanRemoval: true)]
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

    public function getStatus(): MigrationStatus
    {
        return $this->status;
    }

    public function setStatus(MigrationStatus $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function hasEnded(): bool
    {
        return in_array(
            $this->getStatus(),
            [
                MigrationStatus::FINISHED,
                MigrationStatus::FAILURE,
                MigrationStatus::CANCELED,
            ],
            true
        );
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
        return $this->tasks->filter(fn(TaskEntity $task) => $task->getType() === TaskType::BEFORE);
    }

    /**
     * @return Collection<int, TaskEntity>
     */
    public function getFinishedBeforeTasks()
    {
        return $this->getBeforeTasks()->filter(fn(TaskEntity $task) => $task->hasEnded());
    }

    /**
     * @return Collection<int, TaskEntity>
     */
    public function getAfterTasks()
    {
        return $this->tasks->filter(fn(TaskEntity $task) => $task->getType() === TaskType::AFTER);
    }

    /**
     * @return Collection<int, TaskEntity>
     */
    public function getFinishedAfterTasks()
    {
        return $this->getAfterTasks()->filter(fn(TaskEntity $task) => $task->hasEnded());
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

    /**
     * @return Collection<int, MigratorEntity>|MigratorEntity[]
     */
    public function getFinishedMigrators()
    {
        return $this->migrators->filter(fn(MigratorEntity $migrator) => $migrator->hasEnded());
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
