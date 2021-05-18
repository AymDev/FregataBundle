<?php

namespace Fregata\FregataBundle\Command;

use Fregata\Console\MigrationListCommand as FrameworkMigrationListCommand;

class MigrationListCommand extends FrameworkMigrationListCommand
{
    protected static $defaultName = 'fregata:migration:list';
}
