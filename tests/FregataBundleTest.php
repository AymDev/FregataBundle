<?php

namespace Tests\Fregata\FregataBundle;

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\DoctrineOrmMappingsPass;
use Fregata\Configuration\FregataCompilerPass;
use Fregata\FregataBundle\DependencyInjection\Compiler\CommandsCompilerPass;
use Fregata\FregataBundle\DependencyInjection\FregataExtension;
use Fregata\FregataBundle\FregataBundle;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class FregataBundleTest extends TestCase
{
    /**
     * Check if the extension can be loaded
     */
    public function testBundleExtensionIsLoadable(): void
    {
        $bundle = new FregataBundle();
        $extension = $bundle->getContainerExtension();

        self::assertInstanceOf(FregataExtension::class, $extension);
    }

    /**
     * Check if compiler passes are registered
     */
    public function testCompilerPasses(): void
    {
        $container = new ContainerBuilder();
        $bundle = new FregataBundle();

        $bundle->build($container);
        $compilerPasses = $container->getCompilerPassConfig()->getPasses();
        $passClases = array_map('get_class', $compilerPasses);

        self::assertContains(FregataCompilerPass::class, $passClases);
        self::assertContains(CommandsCompilerPass::class, $passClases);
        self::assertContains(DoctrineOrmMappingsPass::class, $passClases);
    }
}
