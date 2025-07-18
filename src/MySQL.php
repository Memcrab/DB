<?php
declare(strict_types=1);

namespace Memcrab\DB;

use Monolog\Logger;
use OpenSwoole\Core\Coroutine\Client\MysqliConfig;
use OpenSwoole\Core\Coroutine\Client\MysqliException;

\mysqli_report(MYSQLI_REPORT_STRICT);

class MySQL extends \mysqli
{
    private Logger $ErrorHandler;

    public function __construct(MysqliConfig $MysqliConfig, Logger $ErrorHandler)
    {
        parent::__construct();

        $this->ErrorHandler = $ErrorHandler;

        foreach ($MysqliConfig->getOptions() as $option => $value) {
            $this->set_opt($option, $value);
        }

        if (@$this->real_connect(
                $MysqliConfig->getHost(),
                $MysqliConfig->getUsername(),
                $MysqliConfig->getPassword(),
                $MysqliConfig->getDbname(),
                $MysqliConfig->getPort(),
                $MysqliConfig->getUnixSocket()
            ) === false) {
            throw new MysqliException("Cant connect to MySQL. " . $this->connect_error, 500);
        }
    }

    public function __destruct()
    {
        try {
            $this->close();
        } catch (\Throwable $e) {
        }
    }

    private function error(\Exception $e, string $qs = ''): void
    {
        $this->ErrorHandler->error('MySQL Exception (name:`' . ($this->name ?? null) . '`): ' . (string)$e . ', SQL:' . $qs);
    }

    public function heartbeat(): void
    {
        $this->query('SELECT 1');
    }

    public function start(): bool
    {
        return $this->begin_transaction();
    }

    public function getField(string $qs, string $field): mixed
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
            $this->error($e, $qs);
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
            $this->error($e, $qs);
            throw $e;
        }
        return $array;
    }

    public function mres(string $var): string
    {
        try {
            return $this->real_escape_string(trim($var));
        } catch (\Exception $e) {
            $this->error($e, $qs);
            throw $e;
        }
    }

    public function amres(array $array): array
    {
        foreach ($array as $key => $value) {
            $array[$key] = $this->mres($value);
        }

        return $array;
    }

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

    public function getObjects(string $qs, string $className = null, array $constructorParams = []): array
    {
        try {
            $objects = [];
            $Result = $this->query($qs);

            while ($Object = $Result->fetch_object($className, $constructorParams)) {
                array_push($objects, $Object);
            }
            $Result->free();
        } catch (\Exception $e) {
            $this->error($e, $qs);
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
            $this->error($e, $qs);
            throw $e;
        }
        return $ResultObject;
    }

    public function getObjectsWithLogic(string $qs, callable $logic, string $className = 'stdClass', array $constructorParams = []): array
    {
        try {
            $objects = array();
            $Result = $this->query($qs);

            while ($Object = $Result->fetch_object($className, $constructorParams)) {
                array_push($objects, $logic($Object, $constructorParams));
            }
            $Result->free();
        } catch (\Exception $e) {
            $this->error($e, $qs);
            throw $e;
        }
        return $objects;
    }

    public function getWork(string $qs)
    {
        try {
            if (!$this->query($qs)) {
                throw new \Exception("Mysql error: " . $this->error);
            }

            return $qs;
        } catch (\Exception $e) {
            $this->error($e, $qs);
            throw $e;
        }
    }

    public function tryWork(string $qs, string $exception, bool|int $exceptionCode = false)
    {
        try {
            if (!$this->query($qs)) {
                if ($exceptionCode === false) {
                    throw new \Exception($exception . $this->error);
                } else {
                    throw new \Exception($exception . $this->error, $exceptionCode);
                }
            }

            return $qs;
        } catch (\Exception $e) {
            $this->error($e, $qs);
            throw $e;
        }
    }

    /**
     * @param string $encryptedData
     * @param string $passphrase
     * 
     * @return string part of MySQL query for encrypting data or string "NULL" if there is no data for encryption
     */
    public function aesEncrypt(?string $encryptedData, string $passphrase): string 
    {
        $string = "NULL";
        if (!empty($encryptedData)) {
            $string = "AES_ENCRYPT('" . $this->mres($encryptedData) . "', UNHEX(SHA2('" . $passphrase . "',512)))";
        } 

        return $string;     
    }

    /**
     * @param string $column column name where need to decrypt data
     * - column name need be passed with a table name prefix
     * @param string $passphrase 
     * - if decrypting passphrase don't match with encrypt passphrase MySQL return NULL value
     *  Notice:
     *  - if your query uses a short table name, don't forget to indicate it as part of the column name
     * 
     * @return string part of MySQL query for decrypting data
     */
    public function aesDecrypt(string $columnName, string $passphrase): string 
    {
        $string = "IF(ISNULL (" . $columnName . " ), NULL , AES_DECRYPT(" . $columnName .", UNHEX(SHA2('". $passphrase ."', 512))) )";
        return $string;
    }

    public function ping(): bool {
        try {
            return @parent::ping();
        } catch (\Exception $e) {
            $this->error($e);
            return false;
        }
    }
}
