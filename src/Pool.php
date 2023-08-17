<?php
declare(strict_types=1);

namespace Memcrab\DB;

use Monolog\Logger;
use OpenSwoole\Coroutine;
use OpenSwoole\Coroutine\Channel;

abstract class Pool extends Channel
{
    protected Logger $ErrorHandler;
    protected int $waitTimeoutPool;       # maximum waiting time from the channel

    const DEFAULT_CAPACITY = 16;
    const PING_INTERVAL = 5;

    abstract protected function connect();

    abstract protected function disconnect($connection);

    abstract protected function checkConnectionForErrors($connection);

    abstract protected function error(\Exception $e);

    public function setErrorHandler(Logger $ErrorHandler): void
    {
        $this->ErrorHandler = $ErrorHandler;
    }

    public function setWaitTimeoutPool(int $waitTimeoutPool): void
    {
        $this->waitTimeoutPool = $waitTimeoutPool;
    }

    public function setConnections(): bool
    {
        try {
            $this->fillConnections();
            $this->startPingConnections();
            return true;
        } catch (\Exception $e) {
            $this->error($e);
            return false;
        }
    }

    public function get()
    {
        $connection = $this->getConnectionFromPool();
        if (!$connection) {
            throw new \Exception("Cant connect to " . get_called_class() . '.', 500);
        }

        return $connection;
    }

    public function put($connection): void
    {
        if ($this->checkConnectionForErrors($connection)) {
            $this->disconnect($connection);
            return;
        }

        if ($this->length() >= $this->capacity) {
            $this->disconnect($connection);
            return;
        }

        if (!$this->push($connection, $this->waitTimeoutPool)) {
            $this->disconnect($connection);
        }
    }

    private function fillConnections(): void
    {
        $diff = $this->capacity - $this->length();
        for ($i = 0; $i < $diff; $i++) {
            $connection = $this->connect();
            if (!$connection) {
                continue;
            }
            if (!$this->push($connection, $this->waitTimeoutPool)) {
                $this->disconnect($connection);
            }
        }
    }

    private function getConnectionFromPool()
    {
        $connection = false;
        if (!$this->isEmpty()) {
            $connection = $this->pop($this->waitTimeoutPool);
        }

        if (!$connection) {
            $connection = $this->connect();
        }

        return $connection;
    }

    private function startPingConnections(): void
    {
        Coroutine::create(function () {
            while (true) {
                Coroutine::sleep(self::PING_INTERVAL);
                $this->fillConnections();
                $connection = $this->pop($this->waitTimeoutPool);

                if (!$connection) continue;

                try {
                    $connection->heartbeat();
                } catch (\Throwable $e) {
                    $this->disconnect($connection);
                    $connection = $this->connect();
                }

                if ($connection) $this->put($connection);
            }
        });
    }
}