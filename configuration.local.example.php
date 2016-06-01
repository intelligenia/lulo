<?php

/**
 * Example function that returns database access credentials.
 * You will have to create a file configuration.local.php in this directory
 * with the implementation of this function.
 * 
 * @return array Array with database access credentials.
 *  */
function get_db_settings(){
    $db_settings = [
        "server" => "<DB SERVER>",
        "user" => "<DB USER>",
        "password" => "<DB PASSWORD>",
        "database" => "<DATABASE>"
    ];
    return $db_settings;
}

?>

