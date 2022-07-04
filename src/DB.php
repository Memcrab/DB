<?php

declare(strict_types=1);

namespace Memcrab\DB;

interface DB
{
    public static function declareConnection(
        string $name,
        string $host,
        int $port,
        string $user,
        string $password,
        string $database,
        \Memcrab\Log\Log $ErrorHandler,
        string $encoding = 'utf8mb4',
        int $waitTimeout = 28800
    ): void;

    public static function shutdown(): void;
    public function setName(string $name): void;
    public function setCredentials(string $host, int $port, string $user, string $password): void;
    public function setDatabase(string $database): void;
    public function setErrorHandler(\Memcrab\Log\Log $ErrorHandler): void;
    public function setConnection(): bool;
    public function query(string $query, int $resultMode);
    public function ping(): bool;
    public function start(): bool;
    public function getField(string  $qs, string $field): mixed;
    public function getRow(string $qs, int $type = MYSQLI_ASSOC): array;
    public function getArray(string $qs, int $type = MYSQLI_ASSOC): array;
    public function getObjects(string $qs, string $className = null, array $constructParrams = []): array;
    public function getObjectWithLogic(string $qs, callable $logic, string $className = 'stdClass', array $constructorParams = []): object;
    public function getObjectsWithLogic(string $qs, callable $logic, string $className = 'stdClass', array $constructorParams = []): array;
    public function mres(string $var): string;
    public function amres(array $array): array;
    public function ramres(array $array): array;
}
