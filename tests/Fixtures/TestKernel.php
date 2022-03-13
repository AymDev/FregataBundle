<?php

namespace Tests\Fregata\FregataBundle\Fixtures;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Fregata\FregataBundle\FregataBundle;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;

/**
 * @internal
 */
class TestKernel extends Kernel
{
    public function registerBundles(): iterable
    {
        return [
            new FrameworkBundle(),
            new DoctrineBundle(),

            new FregataBundle(),
        ];
    }

    public function getProjectDir(): string
    {
        return dirname(__DIR__, 2);
    }

    public function registerContainerConfiguration(LoaderInterface $loader)
    {
        $loader->load(__DIR__.'/config/*.yaml', 'glob');

        $loader->load(static function (ContainerBuilder $container) {
            $container->register('logger', NullLogger::class);
        });
    }

    public function getCacheDir(): string
    {
        return __DIR__ . '/build/cache';
    }

    public function getLogDir(): string
    {
        return __DIR__ . '/build/log';
    }
}
