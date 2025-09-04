<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$current_page = basename($_SERVER['PHP_SELF']);

// Load database and permissions
require_once __DIR__ . "db.php";
require_once __DIR__ . "permissions.php";

// Get unread notification count (only if admin or authorized)
$unread_count = 0;
if (isset($_SESSION['admin_id']) || isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE is_read = 0");
    if ($stmt) {
        $stmt->execute();
        $stmt->bind_result($unread_count);
        $stmt->fetch();
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>MemoGen Admin Panel</title>
    <!-- Custom Styles -->
    <link rel="stylesheet" href="/frontend/includes/admin_style.css" />
    <style>
        body {
            margin: 0;
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #f4f4f4;
            color: #333;
        }

        /* Sidebar Styling */
        .sidebar {
            width: 230px;
            background: #458ed3ff;
            color: #fff;
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            padding-top: 24px;
            box-shadow: 2px 0 8px rgba(0, 0, 0, 0.07);
            display: flex;
            flex-direction: column;
            z-index: 100;
            transition: width 0.2s ease;
        }

        .sidebar.collapsed {
            width: 60px;
        }

        .sidebar-logo {
            display: flex;
            align-items: center;
            padding: 0 20px 24px 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            min-height: 60px;
        }

        .sidebar.collapsed .sidebar-logo span {
            display: none;
        }

        .sidebar-logo svg {
            width: 38px;
            height: 38px;
            margin-right: 12px;
        }

        .sidebar.collapsed .sidebar-logo svg {
            margin-right: 0;
        }

        .sidebar-logo span {
            font-size: 1.22rem;
            font-weight: bold;
            letter-spacing: 1px;
            transition: opacity 0.2s;
        }

        .sidebar-nav {
            flex: 1;
            padding: 24px 0 0 0;
        }

        .sidebar-nav a {
            display: flex;
            align-items: center;
            gap: 12px;
            color: #cfcfcf;
            text-decoration: none;
            padding: 12px 28px;
            font-size: 1.06rem;
            transition: all 0.15s ease;
            white-space: nowrap;
        }

        .sidebar-nav a:hover,
        .sidebar-nav a.active {
            background: rgba(255, 255, 255, 0.15);
            color: #fff;
            border-radius: 6px;
        }

        .sidebar.collapsed .sidebar-nav a span {
            display: none;
        }

        .sidebar.collapsed .sidebar-nav a {
            justify-content: center;
            padding: 12px 0;
        }

        /* Sidebar Actions (Bottom Section) */
        .sidebar-actions {
            padding: 20px 20px 12px 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            transition: padding 0.2s;
        }

        .sidebar.collapsed .sidebar-actions {
            padding-left: 8px;
            padding-right: 8px;
        }

        /* Notification Bell */
        .notification-bell-wrapper {
            display: flex;
            justify-content: center;
            margin-bottom: 16px;
        }

        .notification-bell {
            position: relative;
            display: inline-block;
            text-decoration: none;
        }

        .notification-bell svg {
            width: 26px;
            height: 26px;
            fill: #ffffff;
        }

        .notification-badge {
            position: absolute;
            top: -6px;
            right: -6px;
            background: #e53935;
            color: #fff;
            border-radius: 50%;
            font-size: 0.78rem;
            padding: 2px 6px;
            min-width: 18px;
            text-align: center;
        }

        /* Buttons in Sidebar */
        .sidebar .btn {
            display: block;
            background: #649fe2ff;
            color: #fff;
            padding: 10px 0;
            border-radius: 5px;
            text-align: center;
            font-size: 1rem;
            margin-bottom: 10px;
            text-decoration: none;
            font-weight: bold;
            transition: background 0.2s;
        }

        .sidebar .btn:hover {
            background: #5a8fd0;
        }

        .sidebar-user {
            color: #bbb;
            font-size: 0.98rem;
            margin-bottom: 8px;
            text-align: center;
            white-space: nowrap;
        }

        .sidebar.collapsed .sidebar-user {
            display: none;
        }

        .sidebar-logout-btn {
            background: #e53935;
            color: #fff;
            border: none;
            border-radius: 5px;
            padding: 7px 0;
            width: 100%;
            font-size: 0.97rem;
            cursor: pointer;
            margin-top: 4px;
            font-weight: bold;
            transition: background 0.2s;
        }

        .sidebar-logout-btn:hover {
            background: #c62828;
        }

        .sidebar.collapsed .sidebar-logout-btn {
            padding: 7px 0;
            font-size: 0;
        }

        .sidebar.collapsed .sidebar-logout-btn::after {
            content: "\1F511";
            font-size: 1.2rem;
            color: #fff;
        }

        /* Sidebar Toggle Button */
        .sidebar-toggle {
            position: absolute;
            top: 18px;
            right: -16px;
            width: 32px;
            height: 32px;
            background: #659ee9ff;
            color: #fff;
            border-radius: 50%;
            border: none;
            cursor: pointer;
            z-index: 101;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 1px 1px 6px rgba(0, 0, 0, 0.08);
            transition: right 0.2s, background 0.18s;
        }

        .sidebar.collapsed .sidebar-toggle {
            right: -16px;
        }

        /* Main Content */
        .main-content {
            margin-left: 230px;
            padding: 32px;
            transition: margin-left 0.2s ease;
        }

        .sidebar.collapsed ~ .main-content {
            margin-left: 60px;
        }

        /* Responsive Design */
        @media (max-width: 800px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: static;
                flex-direction: row;
                z-index: 1000;
            }
            .sidebar.collapsed {
                width: 100%;
            }
            .sidebar-toggle {
                position: absolute;
                top: 10px;
                right: 10px;
            }
            .main-content {
                margin-left: 0;
                padding: 16px;
            }
        }

        /* Sidebar Icons */
        .sidebar-icon {
            width: 1.5em;
            height: 1.5em;
            display: inline-block;
            vertical-align: middle;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <button class="sidebar-toggle" id="sidebarToggle" title="Toggle Sidebar">
            <span id="toggleIcon">&#9776;</span>
        </button>

        <!-- Logo -->
        <div class="sidebar-logo">
            <svg viewBox="0 0 38 38" fill="none">
                <circle cx="19" cy="19" r="19" fill="#1565c0"/>
                <ellipse cx="19" cy="15" rx="6" ry="6" fill="#fff"/>
                <ellipse cx="19" cy="28" rx="10" ry="6" fill="#fff" opacity="0.85"/>
                <rect x="26" y="11" width="7" height="13" rx="2" fill="#42a5f5" stroke="#fff" stroke-width="1"/>
                <rect x="28" y="14" width="3" height="1.5" rx="0.5" fill="#fff"/>
                <rect x="28" y="17" width="3" height="1.5" rx="0.5" fill="#fff"/>
            </svg>
            <span>MemoGen</span>
        </div>

        <!-- Navigation -->
        <div class="sidebar-nav">
            <a href="dashboard.php" class="<?= $current_page == 'dashboard.php' ? 'active' : '' ?>">
                <span class="sidebar-icon">&#127968;</span><span>Dashboard</span>
            </a>

            <?php if (current_user_can('can_manage_users')): ?>
            <a href="users.php" class="<?= $current_page == 'users.php' ? 'active' : '' ?>">
                <span class="sidebar-icon">&#128101;</span><span>Users</span>
            </a>
            <?php endif; ?>

            <?php if (current_user_can('can_view_memo')): ?>
            <a href="memos.php" class="<?= $current_page == 'memos.php' ? 'active' : '' ?>">
                <span class="sidebar-icon">&#128196;</span><span>Memorandums</span>
            </a>
            <?php endif; ?>

            <?php if (current_user_can('can_upload_header')): ?>
            <a href="upload_memo_header.php" class="<?= $current_page == 'upload_memo_header.php' ? 'active' : '' ?>">
                <span class="sidebar-icon">&#128221;</span><span>Upload Header</span>
            </a>
            <?php endif; ?>

            <?php if (current_user_can('can_add_department')): ?>
            <a href="department.php" class="<?= $current_page == 'department.php' ? 'active' : '' ?>">
                <span class="sidebar-icon">&#128202;</span><span>Department</span>
            </a>
            <?php endif; ?>

            <!-- ✅ Conditionally Show "Add Memo" Button -->
            <?php if (current_user_can('can_create_memo')): ?>
            <a href="memo_add.php" class="<?= $current_page == 'memo_add.php' ? 'active' : '' ?>">
                <span class="sidebar-icon">&#10010;</span><span>Create Memo</span>
            </a>
            <?php endif; ?>
        </div>

        <!-- Actions (Bottom Section) -->
        <div class="sidebar-actions">
            <!-- Notifications (Optional - Uncomment if needed) -->
            <!--
            <div class="notification-bell-wrapper">
                <a href="notifications.php" class="notification-bell" title="Notifications">
                    <svg viewBox="0 0 24 24">
                        <path d="M12 24c1.3 0 2.4-1 2.5-2.3h-5c.1 1.3 1.2 2.3 2.5 2.3zm6.3-6V11c0-3.1-2-5.8-5-6.6V4a1.3 1.3 0 1 0-2.6 0v.4c-3 .8-5 3.5-5 6.6v7L3 20v1h18v-1l-2.7-2zM19 20H5v-.2l2.8-2.8V11c0-2.9 2.1-5.2 5.2-5.2s5.2 2.3 5.2 5.2v6l2.8 2.8V20z"/>
                    </svg>
                    <?php if ($unread_count > 0): ?>
                        <span class="notification-badge"><?= $unread_count ?></span>
                    <?php endif; ?>
                </a>
            </div>
            -->

            <!-- Profile Button (Available to all) -->
            <a href="profile.php" class="btn">Profile</a>

            <!-- ✅ Only show "+ Add Memos" button if user has permission -->
            <?php if (current_user_can('can_create_memo')): ?>
                <a href="memo_add.php" class="btn">+ Add Memos</a>
            <?php endif; ?>

            <!-- User Name -->
            <div class="sidebar-user">
                <?= htmlspecialchars($_SESSION['user_fullname'] ?? $_SESSION['admin_name'] ?? 'User') ?>
            </div>

            <!-- Logout -->
            <form action="../logout.php" method="post" style="margin: 0;">
                <button type="submit" class="sidebar-logout-btn">Logout</button>
            </form>
        </div>
    </div>

    <!-- Main Content Placeholder -->
    <div class="main-content">
        <!-- Your page content goes here -->
        <h2>Welcome to MemoGen Admin Panel</h2>
        <p>This is the main content area. Replace this with your dashboard, forms, or tables.</p>
    </div>

    <!-- Sidebar Toggle Script -->
    <script>
        const sidebar = document.getElementById('sidebar');
        const sidebarToggle = document.getElementById('sidebarToggle');
        const toggleIcon = document.getElementById('toggleIcon');

        // Toggle sidebar and update icon
        sidebarToggle.onclick = function () {
            sidebar.classList.toggle('collapsed');
            if (sidebar.classList.contains('collapsed')) {
                toggleIcon.innerHTML = '&#9654;'; // ▶
            } else {
                toggleIcon.innerHTML = '&#9776;'; // ☰
            }
            // Save state
            localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
        };

        // Restore state on load
        window.addEventListener('DOMContentLoaded', function () {
            const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
            if (isCollapsed) {
                sidebar.classList.add('collapsed');
                toggleIcon.innerHTML = '&#9654;';
            }
        });
    </script>
</body>
</html>