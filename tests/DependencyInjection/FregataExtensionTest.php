<?php

namespace Tests\Fregata\FregataBundle\Messenger\DependencyInjection;

use Fregata\FregataBundle\DependencyInjection\FregataExtension;
use Fregata\FregataBundle\Messenger\Command\Migration\StartMigrationHandler;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class FregataExtensionTest extends TestCase
{
    public function testHandlersAreRegistered()
    {
        $container = new ContainerBuilder();
        $extension = new FregataExtension();

        $extension->load([], $container);

        $handlers = $container->findTaggedServiceIds('messenger.message_handler');
        $handlers = array_map(
            fn(string $handlerId) => $container->getDefinition($handlerId)->getClass(),
            array_keys($handlers)
        );

        self::assertContains(StartMigrationHandler::class, $handlers);
    }
}
