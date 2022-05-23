<?php

namespace Fregata\FregataBundle\Doctrine\Task;

use Doctrine\ORM\Mapping as ORM;
use Fregata\FregataBundle\Doctrine\ComponentStatus;
use Fregata\FregataBundle\Doctrine\FregataComponentInterface;
use Fregata\FregataBundle\Doctrine\Migration\MigrationEntity;

/**
 * @internal
 */
#[ORM\Entity(repositoryClass: TaskRepository::class)]
#[ORM\Table(name: 'fregata_task')]
class TaskEntity implements FregataComponentInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTime $startedAt = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTime $finishedAt = null;

    #[ORM\Column(type: 'string', enumType: TaskType::class)]
    private ?TaskType $type = null;

    #[ORM\Column(type: 'string', enumType: ComponentStatus::class)]
    private ComponentStatus $status = ComponentStatus::CREATED;

    #[ORM\Column(type: 'text')]
    private ?string $serviceId = null;

    #[ORM\ManyToOne(targetEntity: MigrationEntity::class, inversedBy: 'tasks')]
    #[ORM\JoinColumn(nullable: false)]
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

    public function getType(): ?TaskType
    {
        return $this->type;
    }

    public function setType(TaskType $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function getStatus(): ComponentStatus
    {
        return $this->status;
    }

    public function setStatus(ComponentStatus $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function hasEnded(): bool
    {
        return in_array(
            $this->getStatus(),
            [
                ComponentStatus::FINISHED,
                ComponentStatus::FAILURE,
                ComponentStatus::CANCELED,
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
