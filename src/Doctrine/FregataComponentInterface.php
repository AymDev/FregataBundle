<?php

namespace Fregata\FregataBundle\Doctrine;

use Fregata\FregataBundle\Doctrine\Migration\MigrationStatus;

/**
 * @internal
 */
interface FregataComponentInterface
{
    public function getStartedAt(): ?\DateTimeInterface;

    public function getFinishedAt(): ?\DateTimeInterface;

    public function getStatus(): MigrationStatus|ComponentStatus;
}
