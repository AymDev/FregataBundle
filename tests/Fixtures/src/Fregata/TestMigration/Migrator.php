<?php

namespace App\Fregata\TestMigration;

use Fregata\Migration\Migrator\Component\Executor;
use Fregata\Migration\Migrator\Component\PullerInterface;
use Fregata\Migration\Migrator\Component\PusherInterface;
use Fregata\Migration\Migrator\MigratorInterface;

class Migrator implements MigratorInterface
{
    private Executor $executor;

    public function __construct(Executor $executor)
    {
        $this->executor = $executor;
    }

    public function getPuller(): PullerInterface
    {
        return new class implements PullerInterface {
            private array $data = [
                'foo',
                'bar',
                'baz',
                'boom',
                'cow',
                'milk',
            ];

            public function pull()
            {
                foreach ($this->data as $value) {
                    sleep(1);
                    yield $value;
                }
            }

            public function count(): ?int
            {
                return count($this->data);
            }
        };
    }

    public function getPusher(): PusherInterface
    {
        return new class implements PusherInterface {
            private array $data = [];

            public function push($data): int
            {
                sleep(1);
                $this->data[] = $data;
                return 1;
            }
        };
    }

    public function getExecutor(): Executor
    {
        return $this->executor;
    }
}
