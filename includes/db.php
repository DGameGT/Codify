<?php
define('DB_SERVER', 'DBSERverURL');
define('DB_USERNAME', 'USERNAMEDB');
define('DB_PASSWORD', 'PWDB');
define('DB_NAME', 'DBNAME_YAH');

$mysqli = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

if($mysqli === false){
    die("ERROR: Could not connect. " . $mysqli->connect_error);
}
?>