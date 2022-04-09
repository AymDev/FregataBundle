<?php

namespace Fregata\FregataBundle\Controller;

use Fregata\FregataBundle\Doctrine\Migration\MigrationRepository;
use Fregata\Migration\MigrationRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Routes for the configured migrations
 * @internal
 */
class MigrationController extends AbstractController
{
    /**
     * List the declared migrations
     */
    public function migrationListAction(MigrationRegistry $migrationRegistry): Response
    {
        return $this->render('@Fregata/migration/list.html.twig', [
            'migrations_list' => $migrationRegistry->getAll(),
        ]);
    }

    /**
     * Display the run history of a migration
     */
    public function migrationDetailsAction(
        string $service_id,
        Request $request,
        MigrationRegistry $migrationRegistry,
        MigrationRepository $migrationRepository
    ): Response {
        $service = $migrationRegistry->get($service_id);
        $page = max(1, intval($request->query->get('page')));
        $migrationRuns = $migrationRepository->getPageForService($service_id, $page);

        if (null === $service && 0 === count($migrationRuns)) {
            throw $this->createNotFoundException();
        }

        return $this->render('@Fregata/migration/details.html.twig', [
            'migration_name' => $service_id,
            'service' => $service,
            'migrations_list' => $migrationRuns,
            'pagination_current' => $page,
            'pagination_offset' => MigrationRepository::PAGINATION_OFFSET,
        ]);
    }
}
