<?php

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Tests\Fregata\FregataBundle\Fixtures\TestKernel;

require_once __DIR__ . '/../vendor/autoload.php';

// Create test app database
$kernel = new TestKernel('test', true);
$kernel->boot();

$application = new Application($kernel);
$application->setAutoExit(false);

$application->run(new ArrayInput([
    'command' => 'doctrine:schema:update',
    '--force' => true,
]));

$kernel->shutdown();
