<?php

error_reporting(0);

$host = "localhost";
$username = "u802714156_mccMemoPass";
$password = "MemoPass2025";
$db = "u802714156_mccMemo";
$port = 3306;

$con = mysqli_connect($host, $username, $password, $db, $port);

// Check connection
if (!$con) {
    die("Connection failed: " . mysqli_connect_error());
}

//echo "Connected successfully";

?>
