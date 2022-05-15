<?php

namespace Tests\Fregata\FregataBundle\Messenger\Command\Migration;

use Fregata\FregataBundle\Messenger\FregataMessageInterface;
use Fregata\FregataBundle\Messenger\Command\Migration\StartMigration;
use PHPUnit\Framework\TestCase;

class StartMigrationTest extends TestCase
{
    public function testProperties(): void
    {
        $migrationId = 'test_migration';
        $message = new StartMigration($migrationId);

        self::assertSame($migrationId, $message->getMigrationId());
    }
}
