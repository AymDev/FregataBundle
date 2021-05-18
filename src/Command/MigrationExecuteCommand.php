<?php

namespace Fregata\FregataBundle\Command;

use Fregata\Console\MigrationExecuteCommand as FrameworkMigrationExecuteCommand;

class MigrationExecuteCommand extends FrameworkMigrationExecuteCommand
{
    protected static $defaultName = 'fregata:migration:execute';

    // TODO: add an option to execute async or not
}
