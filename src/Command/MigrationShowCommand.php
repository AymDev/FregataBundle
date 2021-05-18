<?php

namespace Fregata\FregataBundle\Command;

use Fregata\Console\MigrationShowCommand as FrameworkMigrationShowCommand;

class MigrationShowCommand extends FrameworkMigrationShowCommand
{
    protected static $defaultName = 'fregata:migration:show';
}
