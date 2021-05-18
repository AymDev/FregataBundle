<?php

namespace Tests\Fregata\FregataBundle;

use Fregata\FregataBundle\DependencyInjection\FregataExtension;
use Fregata\FregataBundle\FregataBundle;
use PHPUnit\Framework\TestCase;

class FregataBundleTest extends TestCase
{
    /**
     * Check if the extension can be loaded
     */
    public function testBundleExtensionIsLoadable()
    {
        $bundle = new FregataBundle();
        $extension = $bundle->getContainerExtension();

        self::assertInstanceOf(FregataExtension::class, $extension);
    }
}
