<?php

namespace Fregata\FregataBundle\Doctrine\Task;

/**
 * @internal
 */
enum TaskType: string
{
    case BEFORE = 'BEFORE';
    case AFTER = 'AFTER';
}
