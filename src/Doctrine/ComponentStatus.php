<?php

namespace Fregata\FregataBundle\Doctrine;

/**
 * @internal
 */
enum ComponentStatus: string
{
    case CREATED = 'CREATED';
    case RUNNING = 'RUNNING';
    case FINISHED = 'FINISHED';
    case FAILURE = 'FAILURE';
    case CANCELED = 'CANCELED';
}
