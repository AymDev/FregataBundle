<?php

namespace Fregata\FregataBundle\DependencyInjection\Compiler;

use Fregata\Console\CommandHelper;
use Fregata\FregataBundle\Command\MigrationExecuteCommand;
use Fregata\FregataBundle\Command\MigrationListCommand;
use Fregata\FregataBundle\Command\MigrationShowCommand;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

class CommandsCompilerPass implements CompilerPassInterface
{
    /** @var class-string[] */
    private const COMMAND_CLASSES = [
        MigrationListCommand::class,
        MigrationShowCommand::class,
        MigrationExecuteCommand::class,
    ];

    public function process(ContainerBuilder $container)
    {
        // Helpers
        $commandHelperDefinition = new Definition(CommandHelper::class);
        $container->setDefinition(CommandHelper::class, $commandHelperDefinition);

        // Commands
        foreach (self::COMMAND_CLASSES as $commandClass) {
            $commandDefinition = new Definition($commandClass);
            $commandDefinition
                ->setAutowired(true)
                ->addTag('console.command')
            ;

            $container->setDefinition($commandClass, $commandDefinition);
        }
    }
}
