<?php

namespace Fregata\FregataBundle\DependencyInjection;

use Fregata\Configuration\FregataExtension as FrameworkExtension;
use Fregata\FregataBundle\Doctrine\Migration\MigrationRepository;
use Fregata\FregataBundle\Doctrine\Migrator\MigratorRepository;
use Fregata\FregataBundle\Doctrine\Task\TaskRepository;
use Fregata\FregataBundle\Messenger\Command\Migration\StartMigrationHandler;
use Fregata\FregataBundle\Messenger\Command\Task\RunTaskHandler;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class FregataExtension extends FrameworkExtension
{
    private const HANDLERS_ID = 'fregata.messenger.handler';
    private const REPOSITORIES_ID = 'fregata.doctrine.repository';

    public function load(array $configs, ContainerBuilder $container): void
    {
        parent::load($configs, $container);

        // Register Doctrine repositories
        $this->registerDoctrineServices($container);

        // Register Messenger handlers
        $this->registerMessengerServices($container);
    }

    private function registerDoctrineServices(ContainerBuilder $container)
    {
        // Migration
        $migrationRepositoryDefinition = new Definition(MigrationRepository::class);
        $migrationRepositoryDefinition->setAutowired(true);
        $migrationRepositoryDefinition->addTag('doctrine.repository_service');
        $container->setDefinition(self::REPOSITORIES_ID . '.migration', $migrationRepositoryDefinition);
        $container->setDefinition(MigrationRepository::class, $migrationRepositoryDefinition);

        // Migrator
        $migratorRepositoryDefinition = new Definition(MigratorRepository::class);
        $migratorRepositoryDefinition->setAutowired(true);
        $migratorRepositoryDefinition->addTag('doctrine.repository_service');
        $container->setDefinition(self::REPOSITORIES_ID . '.migrator', $migratorRepositoryDefinition);
        $container->setDefinition(MigratorRepository::class, $migratorRepositoryDefinition);

        // Task
        $taskRepositoryDefinition = new Definition(TaskRepository::class);
        $taskRepositoryDefinition->setAutowired(true);
        $taskRepositoryDefinition->addTag('doctrine.repository_service');
        $container->setDefinition(self::REPOSITORIES_ID . '.task', $taskRepositoryDefinition);
        $container->setDefinition(TaskRepository::class, $taskRepositoryDefinition);
    }

    private function registerMessengerServices(ContainerBuilder $container)
    {
        // Start a migration
        $startMigrationHandlerDefinition = new Definition(StartMigrationHandler::class);
        $startMigrationHandlerDefinition->addTag('messenger.message_handler');
        $container->setDefinition(self::HANDLERS_ID . '.start_migration', $startMigrationHandlerDefinition);

        // Execute a task
        $taskDefinitions = array_keys($container->findTaggedServiceIds(self::TAG_TASK));
        $serviceLocator = ServiceLocatorTagPass::register($container, array_combine(
            $taskDefinitions,
            array_map(fn (string $taskId) => new Reference($taskId), $taskDefinitions)
        ));

        $runTaskHandlerDefinition = new Definition(RunTaskHandler::class);
        $runTaskHandlerDefinition
            ->setAutowired(true)
            ->addTag('messenger.message_handler')
            ->setArgument('$serviceLocator', $serviceLocator)
        ;
        $container->setDefinition(self::HANDLERS_ID . '.run_task', $runTaskHandlerDefinition);
    }
}
