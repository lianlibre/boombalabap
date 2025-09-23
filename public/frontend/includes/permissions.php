<?php
// includes/permissions.php

/**
 * Check if current user has a specific permission
 *
 * @param string $permission e.g. 'create_memo', 'upload_header', etc.
 * @return bool
 */
function current_user_can($permission) {
    // Prevent access if not logged in
    if (!isset($_SESSION['role'])) {
        return false;
    }

    $role = $_SESSION['role'];

    global $conn;

    // Validate connection
    if (!$conn) {
        error_log("Database connection not available in permissions.php");
        return false;
    }

    // Prepare query to get role permissions
    $stmt = $conn->prepare("SELECT * FROM roles_permissions WHERE role = ?");
    if (!$stmt) {
        error_log("Failed to prepare statement: " . $conn->error);
        return false;
    }

    $stmt->bind_param("s", $role);
    if (!$stmt->execute()) {
        error_log("Execute failed: " . $stmt->error);
        $stmt->close();
        return false;
    }

    $result = $stmt->get_result();
    $perm = $result->fetch_assoc();
    $stmt->close();

    // If no record found for this role
    if (!$perm) {
        return false;
    }

    // Map permission name to DB column
    $map = [
        'create_memo'       => 'can_create_memo',
        'view_memo'         => 'can_view_memo',
        'upload_header'     => 'can_upload_header',
        'manage_users'      => 'can_manage_users',
        'add_department'    => 'can_add_department',
        'edit_profile'      => 'can_edit_profile',
        'access_dashboard'  => 'can_access_dashboard'
    ];

    $column = $map[$permission] ?? null;

    if (!$column || !array_key_exists($column, $perm)) {
        return false;
    }

    return (bool)$perm[$column];
}

/**
 * Check if user can access /admin/ panel
 *
 * Only users who can access dashboard AND have admin-level role
 * @return bool
 */
function can_access_admin_panel() {
    $admin_roles = [
        'admin',
        'president',
        'vp_academic',
        'vp_student',
        'registrar',
        'guidance',
        'library',
        'scholarship',
        'mis',
        'nstp',
        'dean_bsit', 'dean_bsba', 'dean_bshm', 'dean_bsed', 'dean_beed',
        'dept_head_bsit', 'dept_head_bsba', 'dept_head_bshm', 'dept_head_bsed', 'dept_head_beed',
        'school_counselor',
    ];

    $role = $_SESSION['role'] ?? '';

    // Must be in allowed list AND have access_dashboard permission
    return in_array($role, $admin_roles) && current_user_can('access_dashboard');
}

/**
 * Get student program from role
 * Example: student_bsit → BSIT
 * @return string|null
 */
function get_student_program() {
    $role = $_SESSION['role'] ?? '';
    
    if (strpos($role, 'student_') !== 0) {
        return null; // Not a student role
    }

    $map = [
        'student_bsit' => 'BSIT',
        'student_bshm' => 'BSHM',
        'student_bsba' => 'BSBA',
        'student_bsed' => 'BSED',
        'student_beed' => 'BEED'
    ];

    return $map[$role] ?? null;
}

/**
 * Check if current user is a student
 * @return bool
 */
function is_student_role() {
    $role = $_SESSION['role'] ?? '';
    return strpos($role, 'student_') === 0;
}