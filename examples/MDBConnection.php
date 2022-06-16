<?php

declare(strict_types=1);
require_once __DIR__ . '/../vendor/autoload.php';

use Memcrab\DB\MDB;
MDB::shutdown();
MDB::declareConnection(
    'write',
    "127.0.0.1",
    3306,
    "user",
    "password",
    "databaseName",
    "utf8mb4",
    120
);