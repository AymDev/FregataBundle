<?php

namespace Fregata\FregataBundle\Controller;

use Fregata\FregataBundle\Doctrine\Migration\MigrationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

/**
 * Routes for the Fregata dashboard
 * @internal
 */
class DashboardController extends AbstractController
{
    public function dashboardAction(MigrationRepository $migrationRepository): Response
    {
        $running = $migrationRepository->getRunning();
        $last = null;

        if ($running->isEmpty()) {
            $last = $migrationRepository->getLast();
        }

        return $this->render('@Fregata/dashboard/dashboard.html.twig', [
            'running' => $running,
            'last' => $last,
        ]);
    }
}
