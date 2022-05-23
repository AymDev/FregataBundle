<?php

namespace Fregata\FregataBundle\Doctrine\Migration;

use Fregata\FregataBundle\Doctrine\Task\TaskType;

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

    /**
     * Returns true if the status should trigger a concelation of a component
     */
    public function isCanceling(): bool
    {
        return match ($this) {
            self::CANCELED,
            self::FAILURE => true,
            default => false,
        };
    }

    /**
     * Returns true if the migration is in a compatible status to run a task
     */
    public function canRunTask(TaskType $taskType): bool
    {
        return match ($taskType) {
            TaskType::BEFORE => match ($this) {
                self::CREATED,
                self::BEFORE_TASKS,
                self::CORE_BEFORE_TASKS => true,
                default => false,
            },
            TaskType::AFTER => match ($this) {
                self::MIGRATORS,
                self::CORE_AFTER_TASKS,
                self::AFTER_TASKS => true,
                default => false,
            },
        };
    }

    /**
     * Returns true if the migration is in a compatible status to run a migrator
     */
    public function canRunMigrator(): bool
    {
        return match ($this) {
            self::CREATED,
            self::BEFORE_TASKS,
            self::CORE_BEFORE_TASKS,
            self::MIGRATORS => true,
            default => false,
        };
    }
}
