<?php

namespace Tests\Fregata\FregataBundle\DependencyInjection;

use Fregata\Configuration\FregataExtension as FrameworkExtension;
use Fregata\FregataBundle\Controller\DashboardController;
use Fregata\FregataBundle\Controller\MigrationController;
use Fregata\FregataBundle\Controller\RunController;
use Fregata\FregataBundle\DependencyInjection\FregataExtension;
use Fregata\FregataBundle\Messenger\Command\Migration\StartMigrationHandler;
use Fregata\FregataBundle\Messenger\Command\Migrator\RunMigratorHandler;
use Fregata\FregataBundle\Messenger\Command\Task\RunTaskHandler;
use Fregata\Migration\Migrator\MigratorInterface;
use Fregata\Migration\TaskInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ServiceLocator;

class FregataExtensionTest extends TestCase
{
    public function testHandlersAreRegistered(): void
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
        self::assertContains(RunTaskHandler::class, $handlers);
        self::assertContains(RunMigratorHandler::class, $handlers);
    }

    /**
     * The task handler must have a configured service locator
     */
    public function testTaskHandlerServiceLocatorServices(): void
    {
        $container = new ContainerBuilder();
        $extension = new FregataExtension();

        $task = self::getMockForAbstractClass(TaskInterface::class);
        /** @var class-string<TaskInterface> $taskClass */
        $taskClass = get_class($task);

        $configuration = [
            'migrations' => [
                'test_migration' => [
                    'tasks' => [
                        'before' => [
                            $taskClass,
                        ]
                    ]
                ]
            ]
        ];
        $extension->load([$configuration], $container);

        // Get the service locator
        $serviceLocatorReference = $container->findDefinition('fregata.messenger.handler.run_task')
            ->getArgument('$serviceLocator');
        self::assertInstanceOf(Reference::class, $serviceLocatorReference);

        $serviceLocatorId = (string) $serviceLocatorReference;
        $serviceLocator = $container->get($serviceLocatorId);
        self::assertInstanceOf(ServiceLocator::class, $serviceLocator);

        // Get the task
        $tasks = $container->findTaggedServiceIds(FrameworkExtension::TAG_TASK);
        self::assertCount(1, $tasks);

        // Locate the task service
        $taskId = array_keys($tasks)[0];
        self::assertTrue($serviceLocator->has($taskId));
        $task = $serviceLocator->get($taskId);
        self::assertInstanceOf($taskClass, $task);
    }

    /**
     * The migrator handler must have a configured service locator
     */
    public function testMigratorHandlerServiceLocatorServices(): void
    {
        $container = new ContainerBuilder();
        $extension = new FregataExtension();

        $migrator = self::getMockForAbstractClass(MigratorInterface::class);
        /** @var class-string<MigratorInterface> $migratorClass */
        $migratorClass = get_class($migrator);

        $configuration = [
            'migrations' => [
                'test_migration' => [
                    'migrators' => [
                        $migratorClass,
                    ]
                ]
            ]
        ];
        $extension->load([$configuration], $container);

        // Get the service locator
        $serviceLocatorReference = $container->findDefinition('fregata.messenger.handler.run_migrator')
            ->getArgument('$serviceLocator');
        self::assertInstanceOf(Reference::class, $serviceLocatorReference);

        $serviceLocatorId = (string) $serviceLocatorReference;
        $serviceLocator = $container->get($serviceLocatorId);
        self::assertInstanceOf(ServiceLocator::class, $serviceLocator);

        // Get the migrator
        $migrators = $container->findTaggedServiceIds(FrameworkExtension::TAG_MIGRATOR);
        self::assertCount(1, $migrators);

        // Locate the task service
        $migratorId = array_keys($migrators)[0];
        self::assertTrue($serviceLocator->has($migratorId));
        $task = $serviceLocator->get($migratorId);
        self::assertInstanceOf($migratorClass, $task);
    }

    /**
     * The user interface controllers must be registered
     */
    public function testControllersAreRegistered(): void
    {
        $container = new ContainerBuilder();
        $extension = new FregataExtension();
        $extension->load([], $container);

        $controllers = $container->findTaggedServiceIds('controller.service_arguments');
        $controllers = array_map(
            fn(string $controllerId) => $container->getDefinition($controllerId)->getClass(),
            array_keys($controllers)
        );

        self::assertContains(DashboardController::class, $controllers);
        self::assertContains(MigrationController::class, $controllers);
        self::assertContains(RunController::class, $controllers);
    }
}
