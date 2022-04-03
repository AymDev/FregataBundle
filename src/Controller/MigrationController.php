<?php

namespace Fregata\FregataBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

/**
 * Routes for the configured migrations
 * @internal
 */
class MigrationController extends AbstractController
{
    /**
     * List the declared migrations
     */
    public function migrationListAction(): void
    {
        dd('TODO');
    }

    /**
     * Display the run history of a migration
     */
    public function migrationDetails(): void
    {
        dd('TODO');
    }
}
