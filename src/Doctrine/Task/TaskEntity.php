<?php

namespace Fregata\FregataBundle\Doctrine\Task;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Fregata\FregataBundle\Doctrine\Migration\MigrationEntity;

/**
 * @internal
 * @ORM\Entity(repositoryClass="Fregata\FregataBundle\Doctrine\Task\TaskRepository")
 * @ORM\Table(name="fregata_task")
 */
class TaskEntity
{
    public const TASK_BEFORE = 'BEFORE';
    public const TASK_AFTER  = 'AFTER';

    public const STATUS_CREATED  = 'CREATED';
    public const STATUS_RUNNING  = 'RUNNING';
    public const STATUS_FINISHED = 'FINISHED';
    public const STATUS_FAILURE  = 'FAILURE';
    public const STATUS_CANCELED = 'CANCELED';

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
    private ?string $type = null;

    /**
     * @ORM\Column(type="string", length=50)
     */
    private string $status = self::STATUS_CREATED;

    /**
     * @ORM\Column(type="text")
     */
    private ?string $serviceId = null;

    /**
     * @ORM\ManyToOne(targetEntity="Fregata\FregataBundle\Doctrine\Migration\MigrationEntity", inversedBy="tasks")
     * @ORM\JoinColumn(nullable=false)
     */
    private ?MigrationEntity $migration = null;

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

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;
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

    public function getMigration(): ?MigrationEntity
    {
        return $this->migration;
    }

    public function setMigration(?MigrationEntity $migration): self
    {
        $this->migration = $migration;
        return $this;
    }
}
