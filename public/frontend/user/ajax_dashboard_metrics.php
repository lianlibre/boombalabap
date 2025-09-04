<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
header('Content-Type: application/json');
$user_id = $_SESSION['user_id'] ?? 0;
$total_memos = 0;
$total_notifications = 0;

if ($user_id) {
    $result = $conn->query("SELECT COUNT(*) FROM memos WHERE user_id=$user_id");
    $total_memos = $result ? $result->fetch_row()[0] : 0;

    $result = $conn->query("SELECT COUNT(*) FROM notifications WHERE user_id=$user_id AND is_read=0");
    $total_notifications = $result ? $result->fetch_row()[0] : 0;
}

echo json_encode([
    'total_memos' => $total_memos,
    'total_notifications' => $total_notifications
]);
?>