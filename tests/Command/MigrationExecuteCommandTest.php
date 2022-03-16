<?php

namespace Tests\Fregata\FregataBundle\Command;

use Fregata\FregataBundle\Command\MigrationExecuteCommand;
use Fregata\FregataBundle\Messenger\Command\Migration\StartMigration;
use Fregata\Migration\Migration;
use Fregata\Migration\MigrationRegistry;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\Fregata\FregataBundle\Messenger\AbstractMessengerTestCase;

class MigrationExecuteCommandTest extends AbstractMessengerTestCase
{
    /**
     * The synchronous option must execute the migration synchronously using the framework's command
     */
    public function testSynchronousExecution(): void
    {
        $registry = new MigrationRegistry();
        $registry->add('test-migration', new Migration());

        $command = new MigrationExecuteCommand($registry, $this->getMessageBus());
        $tester = new CommandTester($command);

        $tester->execute([
            'migration' => 'test-migration',
            '--synchronous' => null,
        ]);

        // Command is successful
        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('[OK]', $tester->getDisplay());

        // Synchronous run: no Messenger message
        self::assertCount(0, $this->getMessengerTransport()->get());
    }

    /**
     * Get an error for unknown migration
     */
    public function testFailOnUnknownMigration(): void
    {
        $command = new MigrationExecuteCommand(new MigrationRegistry(), $this->getMessageBus());
        $tester = new CommandTester($command);

        $tester->execute([
            'migration' => 'unknown',
        ]);

        self::assertNotSame(0, $tester->getStatusCode());
        self::assertStringContainsString('[ERROR]', $tester->getDisplay());
    }

    /**
     * A Messenger message must be dispatched to start an asynchronous migration execution
     */
    public function testDispatchMessengerMessage(): void
    {
        $registry = new MigrationRegistry();
        $registry->add('test-migration', new Migration());

        $command = new MigrationExecuteCommand($registry, $this->getMessageBus());
        $tester = new CommandTester($command);

        $tester->execute([
            'migration' => 'test-migration',
        ]);

        // Command is successful
        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('[OK]', $tester->getDisplay());

        $messages = $this->getMessengerTransport()->getSent();
        self::assertCount(1, $messages);

        $message = $messages[0];
        self::assertInstanceOf(StartMigration::class, $message->getMessage());
    }
}
