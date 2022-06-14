<?php

declare(strict_types=1);

namespace Memcrab\DB;

use Memcrab\DB\DB;

\mysqli_report(MYSQLI_REPORT_ALL);

class MDB extends \mysqli implements DB
{
    private static array $connections = [];

    private string $name;
    private int $port = 3306;
    private string $host = 'localhost';
    private string $user;
    private string $password;
    private string $database;

    public function __clone()
    {
    }
    public function __wakeup()
    {
    }

    public static function __callStatic(string $connection, $arguments = []): self|bool
    {
        if (trim($connection) != '' && in_array($connection, self::$connections)) {
            if (!isset(self::$connections[$connection])) {
                throw new \Exception('Uknown database object `' . $connection . '`', 500);
            }
            if (!is_resource(self::$connections[$connection]) || get_resource_type(self::$connections[$connection]) !== 'mysql link') {
                throw new \Exception('Database object is not mysql resource.', 500);
            }

            return self::$connections[$connection];
        } else {
            throw new \Exception('Uknown `' . $connection . '` connection');
        }
    }

    /**
     * @param array $connections
     */
    public static function declareConnection(
        string $name,
        string $host,
        int $port,
        string $user,
        string $password,
        string $database,
        string $encoding = 'utf8mb4',
        int $waitTimeout = 28800
    ): void {
        self::$connections[$name] = new self();
        self::$connections[$name]->setName($name);
        self::$connections[$name]->setDatabase($database);
        self::$connections[$name]->setCredentials($host, $port, $user, $password);
        self::$connections[$name]->options(MYSQLI_OPT_CONNECT_TIMEOUT, 2);
        self::$connections[$name]->options(MYSQLI_SET_CHARSET_NAME, $encoding);
        self::$connections[$name]->options(MYSQLI_OPT_INT_AND_FLOAT_NATIVE, true);
        self::$connections[$name]->options(MYSQLI_OPT_READ_TIMEOUT, $waitTimeout);
        self::$connections[$name]->options(MYSQLI_INIT_COMMAND, "SET TIME_ZONE='" . date('P') . "'");
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function setCredentials(string $host, int $port, string $user, string $password): void
    {
        $this->host = $host;
        $this->port = $port;
        $this->user = $user;
        $this->password = $password;
    }

    public function setDatabase(string $database): void
    {
        $this->database = $database;
    }

    private function error_log(\Exception $e, string $qs = ''): void
    {
        error_log(' DB Exception (name:`' . ($this->name ?? null) . '`): ' .  (string)$e . ', SQL:' . $qs);
    }

    public function setConnection(): void
    {
        try {
            $this->real_connect($this->host, $this->user, $this->password, $this->database, $this->port);
        } catch (\Exception $e) {
            $this->error_log($e);
        }
    }

    public static function shutdown(): void
    {
        foreach (self::$connections as $connection) {
            $connection->close();
        }

        unset(self::$connections);
    }

    public function ping(): bool
    {
        try {
            return parent::ping();
        } catch (\Exception $e) {
            $this->error_log($e);
            return false;
        }
    }

    public function start(): bool
    {
        return $this->begin_transaction();
    }

    /**
     * Get result data as only one field
     * It can be the field's offset, the field's name, or the field's table
     * dot field name (tablename.fieldname). If the column name has been
     * aliased ('select foo as bar from...'), use the alias instead of the
     * column name. If undefined, the first field is retrieved.
     *
     * @param  string        $qs   - SQL (string) query
     * @param  string        field - The name of the field being retrieved.
     * @return string|NULL
     */

    public function getField(string  $qs, string $field): mixed
    {
        return $this->getRow($qs)[$field];
    }

    public function getRow(string $qs, int $type = MYSQLI_ASSOC): array
    {
        try {
            $Result = $this->query($qs);
            $row = $Result->fetch_array($type);
            $Result->free();
        } catch (\Exception $e) {
            $this->error_log($e, $qs);
            $Result->free();
            throw $e;
        }

        return $row;
    }

    public function getArray(string $qs, int $type = MYSQLI_ASSOC): array
    {
        try {
            $Result = $this->query($qs);
            $array = $Result->fetch_all($type);
            $Result->free();
        } catch (\Exception $e) {
            $this->error_log($e, $qs);
            $Result->free();
            throw $e;
        }
        return $array;
    }

    /**
     * @param  $var
     * @return mixed
     */
    public function mres(string $var): string
    {
        return $this->real_escape_string(trim($var));
    }

    /**
     * @param array $array
     */
    public function amres(array $array): array
    {
        foreach ($array as $key => $value) {
            $array[$key] = $this->mres($value);
        }

        return $array;
    }

    /**
     * @param array $array
     */
    public function ramres(array $array): array
    {
        foreach ($array as $key => $item) {
            if (is_array($item)) {
                if (count($item) > 0) {
                    $array[$key] = $this->ramres($array[$key]);
                }
            } else {
                $array[$key] = $this->mres($array[$key]);
            }
        }
        return $array;
    }

    /**
     * @param  $qs
     * @param  $className
     * @param  array $constructorParams
     * @return mixed
     */
    public function getObjects(string $qs, string $className = null, array $constructorParams = []): array
    {
        try {
            $objects = [];
            $Result = $this->query($qs);

            while ($Object =  $Result->fetch_object($className, $constructorParams)) {
                array_push($objects, $Object);
            }
            $Result->free();
        } catch (\Exception $e) {
            $this->error_log($e, $qs);
            $Result->free();
            throw $e;
        }

        return $objects;
    }

    public function getObjectWithLogic(string $qs, callable $logic, string $className = 'stdClass', array $constructorParams = []): object
    {
        try {
            $Result = $this->query($qs);
            $Object = $Result->fetch_object($className, $constructorParams);
            $ResultObject = $logic($Object);
            $Result->free();
        } catch (\Exception $e) {
            $this->error_log($e, $qs);
            $Result->free();
            throw $e;
        }
        return $ResultObject;
    }

    public function getObjectsWithLogic(string $qs, callable $logic, string $className = 'stdClass', array $constructorParams = []): array
    {
        try {
            $objects = array();
            $Result = $this->query($qs);

            while ($Object =  $Result->fetch_object($className, $constructorParams)) {
                array_push($objects, $logic($Object, $constructorParams));
            }
            $Result->free();
        } catch (\Exception $e) {
            $this->error_log($e, $qs);
            $Result->free();
            throw $e;
        }
        return $objects;
    }

    public function __destruct()
    {
        self::__destruct();
        if ($this instanceof \mysqli) {
            $this->close();
        }
    }
}
