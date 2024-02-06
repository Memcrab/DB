<?php

const TABLE_SHORT_NAME = 'shrt';


// When used in the 'generateSelect' function in factories - you must specify 
// a prefix from the short table name and an alias for the column name
// The alias must match the column name in the table
function generateSelect(? \Memcrab\DB\MySQL $DB, ?string $passphrase): array
{
    return [
        TABLE_SHORT_NAME . '.*',
        $DB->aesDecrypt(TABLE_SHORT_NAME . '.name', $passphrase) . " as `name`" ,
        $DB->aesDecrypt(TABLE_SHORT_NAME . '.journal', $passphrase) . " as `journal`" ,
        $DB->aesDecrypt(TABLE_SHORT_NAME . '.description', $passphrase) . " as `description`" ,
    ];
}