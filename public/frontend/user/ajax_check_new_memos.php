<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";

$user_id = $_SESSION['user_id'];

// Get user info
$user_stmt = $conn->prepare("SELECT department, last_checked FROM users WHERE id = ?");
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user = $user_result->fetch_assoc();
$user_stmt->close();

$user_department = $user['department'];
$last_checked = $user['last_checked'] ?? '1970-01-01 00:00:00';

// Query: count memos created after last checked, addressed to this department or 'All'
$sql = "
    SELECT COUNT(DISTINCT m.id) AS new_memo_count
    FROM memos m
    JOIN memo_recipients r ON m.id = r.memo_id
    WHERE (r.department = ? OR r.department = 'All')
      AND m.archived = 0
      AND m.created_at > ?
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $user_department, $last_checked);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$stmt->close();

$new_memo_count = $row['new_memo_count'] ?? 0;

// If there are new memos, update last_checked
if ($new_memo_count > 0) {
    $now = date('Y-m-d H:i:s');
    $update_stmt = $conn->prepare("UPDATE users SET last_checked = ? WHERE id = ?");
    $update_stmt->bind_param("si", $now, $user_id);
    $update_stmt->execute();
    $update_stmt->close();
}

// Return JSON response
echo json_encode([
    "new_memo_count" => $new_memo_count
]);
