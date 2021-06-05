<?php

namespace Fregata\FregataBundle;

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\DoctrineOrmMappingsPass;
use Fregata\Configuration\FregataCompilerPass;
use Fregata\FregataBundle\DependencyInjection\Compiler\CommandsCompilerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * @internal
 */
class FregataBundle extends Bundle
{
    public function build(ContainerBuilder $container)
    {
        parent::build($container);

        // Minimal services from the framework
        $container->addCompilerPass(new FregataCompilerPass());

        // Console commands
        $container->addCompilerPass(new CommandsCompilerPass());

        // Register Doctrine entities
        $container->addCompilerPass(DoctrineOrmMappingsPass::createAnnotationMappingDriver(
            [__NAMESPACE__ . '\Doctrine'],
            [__DIR__ . '/Doctrine']
        ));
    }
}
