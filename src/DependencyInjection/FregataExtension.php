<?php

namespace Fregata\FregataBundle\DependencyInjection;

use Fregata\Configuration\FregataExtension as FrameworkExtension;
use Fregata\FregataBundle\Messenger\Command\Migration\StartMigrationHandler;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

class FregataExtension extends FrameworkExtension
{
    private const HANDLERS_ID = 'fregata.messenger.handler';

    public function load(array $configs, ContainerBuilder $container)
    {
        parent::load($configs, $container);

        // Register Messenger handlers
        $this->registerMessengerServices($container);
    }

    private function registerMessengerServices(ContainerBuilder $container)
    {
        // Start a migration
        $startMigrationHandlerDefinition = new Definition(StartMigrationHandler::class);
        $startMigrationHandlerDefinition->addTag('messenger.message_handler');
        $container->setDefinition(self::HANDLERS_ID . '.start_migration', $startMigrationHandlerDefinition);
    }
}
