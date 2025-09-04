<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";

$id = intval($_GET['id']);
// Delete user's memos
$conn->query("DELETE FROM memos WHERE user_id=$id");
// Delete user
$conn->query("DELETE FROM users WHERE id=$id");
header("Location: users.php?msg=deleted");
exit;
?>