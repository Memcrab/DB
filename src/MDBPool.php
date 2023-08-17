<?php
declare(strict_types=1);

namespace Memcrab\DB;

use OpenSwoole\Core\Coroutine\Client\MysqliConfig;

class MDBPool extends Pool
{
    private static array $connections = [];
    private string $name;
    private MysqliConfig $MysqliConfig;

    public function __clone()
    {
    }

    public function __wakeup()
    {
    }

    public static function __callStatic(string $connection, $arguments = []): self|bool
    {
        if (isset(self::$connections[$connection])) {
            if (!isset(self::$connections[$connection])) {
                throw new \Exception('Unknown database object `' . $connection . '`', 500);
            }
            return self::$connections[$connection];
        } else {
            throw new \Exception('Undefined connection `' . $connection . '` to MDBPool, please declare it first.');
        }
    }

    public static function declareConnection(
        string          $name,
        string          $host,
        int             $port,
        string          $user,
        string          $password,
        string          $database,
        \Monolog\Logger $ErrorHandler,
        int             $waitTimeout,
        int             $waitTimeoutPool,
        string          $encoding = 'utf8mb4',
        int             $capacity = self::DEFAULT_CAPACITY,
    ): void
    {
        self::$connections[$name] = new self($capacity);
        self::$connections[$name]->MysqliConfig = (new MysqliConfig())
            ->withHost($host)
            ->withPort($port)
            ->withDbName($database)
            ->withCharset($encoding)
            ->withUsername($user)
            ->withPassword($password)
            ->withOptions([
                MYSQLI_OPT_CONNECT_TIMEOUT => 2,
                MYSQLI_SET_CHARSET_NAME => $encoding,
                MYSQLI_OPT_INT_AND_FLOAT_NATIVE => true,
                MYSQLI_OPT_READ_TIMEOUT => $waitTimeout,
                MYSQLI_INIT_COMMAND => "SET TIME_ZONE='" . date('P') . "'",
            ]);
        self::$connections[$name]->setName($name);
        self::$connections[$name]->setWaitTimeoutPool($waitTimeoutPool);
        self::$connections[$name]->setErrorHandler($ErrorHandler);
        \register_shutdown_function("Memcrab\DB\MDBPool::shutdown");
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    protected function error(\Exception $e): void
    {
        $this->ErrorHandler->error('MDBPool Exception (name:`' . ($this->name ?? null) . '`): ' . $e);
    }

    protected function connect(): \mysqli|bool
    {
        try {
            return (new MySQL($this->MysqliConfig, $this->ErrorHandler));
        } catch (\Exception $e) {
            $this->error($e);
            return false;
        }
    }

    protected function disconnect($connection): bool
    {
        try {
            $connection->close();
            return true;
        } catch (\Exception $e) {
            $this->error($e);
            return false;
        }
    }

    protected function checkConnectionForErrors($connection): bool
    {
        if (!empty($connection->error_list)) {
            foreach ($connection->error_list as $error) {
                if ($error['errno'] >= 2000) {
                    return true;
                }
            }
        }
        return false;
    }

    public function __destruct()
    {
        while (true) {
            if (!$this->isEmpty()) {
                $connection = $this->pop($this->waitTimeoutPool);
                $this->disconnect($connection);
            } else {
                break;
            }
        }
        $this->close();
    }

    public static function shutdown(): void
    {
        foreach (self::$connections as $key => $connection) {
            unset(self::$connections[$key]);
        }
    }
}