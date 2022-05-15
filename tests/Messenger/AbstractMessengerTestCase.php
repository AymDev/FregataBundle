<?php

namespace Tests\Fregata\FregataBundle\Messenger;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\Test\TestLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Middleware\SendMessageMiddleware;
use Symfony\Component\Messenger\Transport\InMemoryTransport;
use Symfony\Component\Messenger\Transport\Sender\SendersLocatorInterface;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;

abstract class AbstractMessengerTestCase extends KernelTestCase
{
    protected ?EntityManagerInterface $entityManager = null;
    protected ?MessageBusInterface $messageBus = null;
    protected ?InMemoryTransport $messengerTransport = null;
    protected ?TestLogger $logger = null;

    protected function setUp(): void
    {
        parent::setUp();

        self::bootKernel();

        $this->getEntityManager()->getConnection()->executeStatement(
            /** @lang SQLite */
            <<<SQL
            PRAGMA foreign_keys = OFF;

            DROP TABLE IF EXISTS fregata_migration;
            CREATE TABLE fregata_migration (
                id INTEGER PRIMARY KEY, 
                started_at TEXT DEFAULT NULL, 
                finished_at TEXT DEFAULT NULL, 
                status TEXT NOT NULL, 
                service_id TEXT NOT NULL
            );

            DROP TABLE IF EXISTS fregata_task;
            CREATE TABLE fregata_task (
                id INTEGER PRIMARY KEY, 
                migration_id INTEGER NOT NULL, 
                started_at TEXT DEFAULT NULL, 
                finished_at TEXT DEFAULT NULL, 
                type TEXT NOT NULL, 
                status TEXT NOT NULL, 
                service_id TEXT NOT NULL,
                FOREIGN KEY (migration_id) REFERENCES fregata_migration(id)
            );

            DROP TABLE IF EXISTS fregata_migrator;
            CREATE TABLE fregata_migrator (
                id INTEGER PRIMARY KEY, 
                migration_id INTEGER NOT NULL, 
                started_at TEXT DEFAULT NULL, 
                finished_at TEXT DEFAULT NULL, 
                status TEXT NOT NULL, 
                service_id TEXT NOT NULL, 
                FOREIGN KEY (migration_id) REFERENCES fregata_migration(id)
            );

            DROP TABLE IF EXISTS migrator_entity_migrator_entity;
            CREATE TABLE migrator_entity_migrator_entity (
                migrator_entity_source INTEGER NOT NULL, 
                migrator_entity_target INTEGER NOT NULL, 
                PRIMARY KEY(migrator_entity_source, migrator_entity_target),
                FOREIGN KEY (migrator_entity_source) REFERENCES fregata_migrator(id),
                FOREIGN KEY (migrator_entity_target) REFERENCES fregata_migrator(id)
            );

            PRAGMA foreign_keys = ON;
            SQL
        );
    }

    protected function getEntityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        return $entityManager;
    }

    protected function getLogger(): TestLogger
    {
        $this->logger ??= new TestLogger();
        return $this->logger;
    }

    protected function getMessengerTransport(): InMemoryTransport
    {
        if (null === $this->messengerTransport) {
            $this->messengerTransport = new InMemoryTransport(new PhpSerializer());
        }

        return $this->messengerTransport;
    }

    protected function getMessageBus(): MessageBusInterface
    {
        if (null === $this->messageBus) {
            $sendersLocator = new class ($this->getMessengerTransport()) implements SendersLocatorInterface {
                private InMemoryTransport $transport;

                public function __construct(InMemoryTransport $transport)
                {
                    $this->transport = $transport;
                }

                public function getSenders(Envelope $envelope): iterable
                {
                    return ['async' => $this->transport];
                }
            };

            $this->messageBus = new MessageBus([
                new SendMessageMiddleware($sendersLocator),
            ]);
        }

        return $this->messageBus;
    }
}
