<?php

namespace Tests\Fregata\FregataBundle\DependencyInjection;

use Fregata\Configuration\FregataExtension as FrameworkExtension;
use Fregata\FregataBundle\DependencyInjection\FregataExtension;
use Fregata\FregataBundle\Messenger\Command\Migration\StartMigrationHandler;
use Fregata\FregataBundle\Messenger\Command\Task\RunTaskHandler;
use Fregata\Migration\TaskInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ServiceLocator;

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
        self::assertContains(RunTaskHandler::class, $handlers);
    }

    /**
     * The task handler must have a configured service locator
     */
    public function testTaskHandlerServiceLocatorServices(): void
    {
        $container = new ContainerBuilder();
        $extension = new FregataExtension();

        $task = self::getMockForAbstractClass(TaskInterface::class);
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
}
