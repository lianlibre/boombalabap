<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once 'auth_admin.php'; // 👈 Don't forget admin check!

// 🔐 Admin-only access
if ($_SESSION['role'] !== 'admin') {
    die("Access denied. Admins only.");
}

if (!isset($_POST['user_id']) || !isset($_POST['confirm_delete'])) {
    header("Location: users.php?error=invalid_request");
    exit;
}

$id = intval($_POST['user_id']);

if ($id <= 0) {
    header("Location: users.php?error=invalid_id");
    exit;
}

// Optional: Add CSRF protection in real apps

// Start transaction for safety (optional but good)
$conn->autocommit(FALSE);

// Delete user's memos first (foreign key constraint safety)
$conn->query("DELETE FROM memos WHERE user_id = $id");

// Then delete the user
$conn->query("DELETE FROM users WHERE id = $id");

if ($conn->error) {
    $conn->rollback();
    error_log("Delete failed: " . $conn->error);
    header("Location: users.php?error=delete_failed");
} else {
    $conn->commit();
    header("Location: users.php?success=user_deleted");
}

$conn->autocommit(TRUE);
exit;
?>