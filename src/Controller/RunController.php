<?php

namespace Fregata\FregataBundle\Controller;

use Fregata\FregataBundle\Doctrine\Migration\MigrationRepository;
use Fregata\FregataBundle\Form\StartMigration\StartMigrationForm;
use Fregata\FregataBundle\Messenger\Command\Migration\StartMigration;
use Fregata\Migration\MigrationRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Routes for the migration runs
 * @internal
 */
class RunController extends AbstractController
{
    /**
     * Execute a new migration
     */
    public function startNewRunAction(Request $request, MigrationRegistry $migrationRegistry, MessageBusInterface $bus): Response
    {
        $startMigrationForm = $this->createForm(StartMigrationForm::class, null, [
            'migrations' => $migrationRegistry->getAll(),
        ]);
        $startMigrationForm->handleRequest($request);

        if ($startMigrationForm->isSubmitted() && $startMigrationForm->isValid()) {
            $migrationName = $startMigrationForm->get('migration')->getData();
            $bus->dispatch(new StartMigration($migrationName));

            $this->addFlash(
                'success',
                sprintf('A new run for the "%s" migration should be starting soon.', $migrationName)
            );
        }

        return $this->render('@Fregata/run/new.html.twig', [
            'start_migration_form' => $startMigrationForm->createView(),
        ]);
    }

    /**
     * Display the complete run history
     */
    public function runHistoryAction(Request $request, MigrationRepository $migrationRepository): Response
    {
        $page = max(1, intval($request->query->get('page')));
        $migrationRuns = $migrationRepository->getPage($page);

        return $this->render('@Fregata/run/history.html.twig', [
            'migrations_list' => $migrationRuns,
            'pagination_current' => $page,
            'pagination_offset' => MigrationRepository::PAGINATION_OFFSET,
        ]);
    }

    /**
     * Display a specific migration run details
     */
    public function runDetailsAction(int $id, MigrationRepository $migrationRepository, MigratorSorter $migratorSorter): Response
    {
        $migration = $migrationRepository->find($id);
        if (null === $migration) {
            throw $this->createNotFoundException();
        }

        return $this->render('@Fregata/run/details.html.twig', [
            'migration' => $migration,
            'migrator_groups' => $migratorSorter->sort($migration),
        ]);
    }
}
