<?php
/* $conn = new mysqli("localhost", "u802714156_mccMemoPass", "MemoPass2025", "u802714156_mccMemo");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
} */

    $conn = new mysqli("localhost", "root", "", "generator_db");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
