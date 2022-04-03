<?php

namespace Fregata\FregataBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
    public function runHistoryAction(): Response
    {
        dd('TODO');
    }

    /**
     * Display a specific migration run details
     */
    public function runDetails(): Response
    {
        dd('TODO');
    }
}
