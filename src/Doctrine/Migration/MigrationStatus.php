<?php

namespace Fregata\FregataBundle\Doctrine\Migration;

/**
 * @internal
 */
enum MigrationStatus: string
{
    /** Migration has only been saved to the database */
    case CREATED = 'CREATED';
    case BEFORE_TASKS = 'BEFORE_TASKS';
    case CORE_BEFORE_TASKS = 'CORE_BEFORE_TASKS';
    case MIGRATORS = 'MIGRATORS';
    case CORE_AFTER_TASKS = 'CORE_AFTER_TASKS';
    case AFTER_TASKS = 'AFTER_TASKS';
    case FINISHED = 'FINISHED';
    case FAILURE = 'FAILURE';
    case CANCELED = 'CANCELED';
}
