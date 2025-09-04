<?php
// includes/auth.php
session_start();
require_once "db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

// Reload permissions on every page
if (isset($_SESSION['role'])) {
    $stmt = $conn->prepare("SELECT * FROM roles_permissions WHERE role = ?");
    $stmt->bind_param("s", $_SESSION['role']);
    $stmt->execute();
    $result = $stmt->get_result();
    $permissions = $result->fetch_assoc();

    if (!$permissions) {
        $permissions = [
            'can_create_memo' => 0,
            'can_view_memo' => 1,
            'can_upload_header' => 0,
            'can_manage_users' => 0,
            'can_add_department' => 0,
            'can_edit_profile' => 1,
            'can_access_dashboard' => 1
        ];
    }
    $_SESSION['permissions'] = $permissions;
}
?>