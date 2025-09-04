<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: memos.php?msg=invalid");
    exit;
}

$memo_id = intval($_GET['id']);

// Make sure the memo belongs to the user (for security)
$stmt = $conn->prepare("SELECT id FROM memos WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $memo_id, $user_id);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows === 0) {
    $stmt->close();
    header("Location: memos.php?msg=forbidden");
    exit;
}
$stmt->close();

// Archive the memo (set archived=1)
$stmt = $conn->prepare("UPDATE memos SET archived = 1 WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $memo_id, $user_id);
$stmt->execute();
$stmt->close();

header("Location: memos.php?msg=archived");
exit;
?>