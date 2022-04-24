<?php

namespace Fregata\FregataBundle\Twig;

use Fregata\Migration\Migrator\MigratorInterface;
use Fregata\Migration\TaskInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * @internal
 */
final class FregataTwigExtension extends AbstractExtension
{
    /** @var array<string, class-string<TaskInterface>> */
    private array $taskClassMap;
    /** @var array<string, class-string<MigratorInterface>> */
    private array $migratorClassMap;

    /**
     * @param array<string, class-string<TaskInterface>> $taskClassMap
     * @param array<string, class-string<MigratorInterface>> $migratorClassMap
     */
    public function __construct(array $taskClassMap, array $migratorClassMap)
    {
        $this->taskClassMap = $taskClassMap;
        $this->migratorClassMap = $migratorClassMap;
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('fregata_class', [$this, 'getObjectClass']),
            new TwigFilter('fregata_task_class', [$this, 'getTaskClass']),
            new TwigFilter('fregata_migrator_class', [$this, 'getMigratorClass']),
        ];
    }

    public function getObjectClass(object $obj): string
    {
        return get_class($obj);
    }

    /**
     * @return class-string<TaskInterface>|null
     */
    public function getTaskClass(string $serviceId): ?string
    {
        return $this->taskClassMap[$serviceId] ?? null;
    }

    /**
     * @return class-string<MigratorInterface>|null
     */
    public function getMigratorClass(string $serviceId): ?string
    {
        return $this->migratorClassMap[$serviceId] ?? null;
    }
}
