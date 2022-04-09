<?php

namespace Fregata\FregataBundle\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * @internal
 */
final class FregataTwigExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('class', [$this, 'getObjectClass']),
        ];
    }

    public function getObjectClass(object $obj): string
    {
        return get_class($obj);
    }
}
