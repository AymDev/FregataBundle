<?php

namespace Tests\Fregata\FregataBundle\DependencyInjection\Compiler;

use Fregata\FregataBundle\Command\MigrationExecuteCommand;
use Fregata\FregataBundle\Command\MigrationListCommand;
use Fregata\FregataBundle\Command\MigrationShowCommand;
use Fregata\FregataBundle\DependencyInjection\Compiler\CommandsCompilerPass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

class CommandsCompilerPassTest extends TestCase
{
    /**
     * Commands must be registered and tagged
     */
    public function testCommandsRegistration(): void
    {
        $commandsPass = new CommandsCompilerPass();
        $container = new ContainerBuilder();

        $commandsPass->process($container);

        $commandIds = $container->findTaggedServiceIds('console.command');
        $commandClasses = array_map(
            fn(string $class) => $container->getDefinition($class)->getClass(),
            array_keys($commandIds)
        );

        self::assertContains(MigrationListCommand::class, $commandClasses);
        self::assertContains(MigrationShowCommand::class, $commandClasses);
        self::assertContains(MigrationExecuteCommand::class, $commandClasses);
    }
}
