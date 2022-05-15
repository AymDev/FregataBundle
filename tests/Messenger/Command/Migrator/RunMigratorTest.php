<?php

namespace Tests\Fregata\FregataBundle\Messenger\Command\Migrator;

use Fregata\FregataBundle\Doctrine\Migrator\MigratorEntity;
use Fregata\FregataBundle\Messenger\Command\Migrator\RunMigrator;
use Fregata\FregataBundle\Messenger\FregataMessageInterface;
use PHPUnit\Framework\TestCase;

class RunMigratorTest extends TestCase
{
    public function testProperties(): void
    {
        $id = 42;

        $entity = self::createMock(MigratorEntity::class);
        $entity->expects(self::atLeastOnce())
            ->method('getId')
            ->willReturn($id);

        $message = new RunMigrator($entity);
        self::assertSame($id, $message->getMigratorId());
    }
}
