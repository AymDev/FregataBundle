<?php

namespace Fregata\FregataBundle\Command;

use Fregata\Console\MigrationExecuteCommand as FrameworkMigrationExecuteCommand;
use Fregata\FregataBundle\Messenger\Command\Migration\StartMigration;
use Fregata\Migration\MigrationRegistry;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

class MigrationExecuteCommand extends FrameworkMigrationExecuteCommand
{
    protected static $defaultName = 'fregata:migration:execute';
    private MigrationRegistry $migrationRegistry;
    private MessageBusInterface $bus;

    public function __construct(MigrationRegistry $migrationRegistry, MessageBusInterface $bus)
    {
        $this->migrationRegistry = $migrationRegistry;
        $this->bus = $bus;

        parent::__construct($migrationRegistry);
    }

    protected function configure(): void
    {
        parent::configure();

        $this->addOption(
            'synchronous',
            's',
            InputOption::VALUE_NONE,
            'Run the migration synchronously, without Symfony Messenger.'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Let the framework command handle the migration
        if (true === $input->getOption('synchronous')) {
            return parent::execute($input, $output);
        }

        /** @var string $migrationName */
        $migrationName = $input->getArgument('migration');
        $io = new SymfonyStyle($input, $output);

        // Migration does not exist
        if (null === $this->migrationRegistry->get($migrationName)) {
            $io->error(sprintf('No migration registered with the name "%s".', $migrationName));
            return 1;
        }

        // Confirm execution
        if ($input->hasOption('no-interaction') && false === $input->getOption('no-interaction')) {
            $confirmationMessage = sprintf('Confirm execution of the "%s" migration ?', $migrationName);
            $confirmation = $io->confirm($confirmationMessage, false);

            if (false === $confirmation) {
                $io->error('Aborting.');
                return 1;
            }
        }


        $this->bus->dispatch(new StartMigration($migrationName));

        $io->success(sprintf('Migration "%s" started', $migrationName));
        $io->newLine();

        return 0;
    }
}
