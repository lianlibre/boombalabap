<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";

$user_id = $_SESSION['user_id'] ?? null;

if ($user_id) {
    $now = date("Y-m-d H:i:s");
    $stmt = $conn->prepare("UPDATE users SET last_checked = ? WHERE id = ?");
    $stmt->bind_param("si", $now, $user_id);
    $stmt->execute();
    $stmt->close();

    echo json_encode([
        "status" => "success",
        "last_checked" => $now
    ]);
} else {
    echo json_encode([
        "status" => "error",
        "message" => "User not logged in"
    ]);
}
