<!-- admin/auth_admin.php -->
<?php

require_once '../includes/permissions.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

if (!can_access_admin_panel()) {
    http_response_code(403);
    die("You are not authorized to access the admin panel.");
}
?>