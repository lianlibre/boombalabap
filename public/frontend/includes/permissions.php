<?php
// includes/permissions.php

function current_user_can($permission) {
    // Get role from session
    $role = $_SESSION['role'] ?? $_SESSION['user_role'] ?? '';

    if (!$role) {
        return false;
    }

    // Fetch permissions from database
    global $conn; // Assuming $conn is available in includes/db.php
    $stmt = $conn->prepare("SELECT * FROM roles_permissions WHERE role = ?");
    $stmt->bind_param("s", $role);
    $stmt->execute();
    $result = $stmt->get_result();
    $perm = $result->fetch_assoc();

    if (!$perm) {
        return false;
    }

    // Map permission key to column name
    $map = [
        'create_memo' => 'can_create_memo',
        'view_memo' => 'can_view_memo',
        'upload_header' => 'can_upload_header',
        'manage_users' => 'can_manage_users',
        'add_department' => 'can_add_department',
        'edit_profile' => 'can_edit_profile',
        'access_dashboard' => 'can_access_dashboard'
    ];

    $column = $map[$permission] ?? null;

    if (!$column) {
        return false;
    }

    return (bool) $perm[$column];
}

function can_access_admin_panel() {
    $allowed_roles = [
        'admin', 'dept_head_bsit', 'dept_head_bsba', 'dept_head_bshm',
        'dept_head_bsed', 'dept_head_beed', 'library', 'soa', 'guidance', 'school_counselor'
    ];
    $role = $_SESSION['role'] ?? $_SESSION['user_role'] ?? '';
    return in_array($role, $allowed_roles);
}
?>