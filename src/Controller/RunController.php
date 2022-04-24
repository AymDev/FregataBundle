<?php

namespace Fregata\FregataBundle\Controller;

use Fregata\FregataBundle\Doctrine\Migration\MigrationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Routes for the migration runs
 * @internal
 */
class RunController extends AbstractController
{
    /**
     * Execute a new migration
     */
    public function startNewRunAction(): Response
    {
        dd('TODO');
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
