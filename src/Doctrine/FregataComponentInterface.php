<?php

namespace Fregata\FregataBundle\Doctrine;

interface FregataComponentInterface
{
    public function getStartedAt(): ?\DateTimeInterface;

    public function getFinishedAt(): ?\DateTimeInterface;

    public function getStatus(): string;
}