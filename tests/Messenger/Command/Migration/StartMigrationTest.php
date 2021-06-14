<?php

namespace Tests\Fregata\FregataBundle\Messenger\Command\Migration;

use Fregata\FregataBundle\Messenger\FregataMessageInterface;
use Fregata\FregataBundle\Messenger\Command\Migration\StartMigration;
use PHPUnit\Framework\TestCase;

class StartMigrationTest extends TestCase
{
    /**
     * Every message must implement the "marker" interface in order to be easily routed to a transport
     */
    public function testMessageImplementsMarkerInterface()
    {
        $message = new StartMigration('some_migration');
        self::assertInstanceOf(FregataMessageInterface::class, $message);
    }

    public function testProperties()
    {
        $migrationId = 'test_migration';
        $message = new StartMigration($migrationId);

        self::assertSame($migrationId, $message->getMigrationId());
    }
}
