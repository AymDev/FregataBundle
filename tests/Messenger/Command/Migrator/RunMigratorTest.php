<?php

namespace Tests\Fregata\FregataBundle\Messenger\Command\Migrator;

use Fregata\FregataBundle\Doctrine\Migrator\MigratorEntity;
use Fregata\FregataBundle\Messenger\Command\Migrator\RunMigrator;
use Fregata\FregataBundle\Messenger\FregataMessageInterface;
use PHPUnit\Framework\TestCase;

class RunMigratorTest extends TestCase
{
    /**
     * Every message must implement the "marker" interface in order to be easily routed to a transport
     */
    public function testMessageImplementsMarkerInterface(): void
    {
        $entity = self::createMock(MigratorEntity::class);
        $entity->expects(self::once())
            ->method('getId')
            ->willReturn(42);

        $message = new RunMigrator($entity);
        self::assertInstanceOf(FregataMessageInterface::class, $message);
    }

    public function testProperties(): void
    {
        $id = 42;

        $entity = self::createMock(MigratorEntity::class);
        $entity->expects(self::once())
            ->method('getId')
            ->willReturn($id);

        $message = new RunMigrator($entity);
        self::assertSame($id, $message->getMigratorId());
    }
}
