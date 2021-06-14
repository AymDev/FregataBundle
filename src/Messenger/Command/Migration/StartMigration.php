<?php

namespace Fregata\FregataBundle\Messenger\Command\Migration;

use Fregata\FregataBundle\Messenger\FregataMessageInterface;

/**
 * @internal
 * Initial message sent to start a migration by creating database entries
 */
class StartMigration implements FregataMessageInterface
{
    private string $migrationId;

    public function __construct(string $migrationId)
    {
        $this->migrationId = $migrationId;
    }

    public function getMigrationId(): string
    {
        return $this->migrationId;
    }
}
