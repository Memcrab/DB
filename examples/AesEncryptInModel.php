<?php

/** encrypted columns in current sample are:
 * name
 * journal
 * description
*/

function getSqlProperties(\Memcrab\DB\MySQL $DB, string $passphrase=null): array
{
    $object = new stdClass;
    return [
        " userId = " . $object->userId,
        " name = " . $DB->aesEncrypt($object->name, $passphrase),
        " journal = " . $DB->aesEncrypt($object->journal, $passphrase),
        " description = " . $DB->aesEncrypt($object->description, $passphrase),
        " trash = " . (int)$object->trash,
    ];
}