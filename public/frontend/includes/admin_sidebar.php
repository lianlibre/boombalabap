<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$current_page = basename($_SERVER['PHP_SELF']);

require_once __DIR__ . "/db.php";
require_once __DIR__ . "/permissions.php";

$unread_count = 0;
if (isset($_SESSION['admin_id'])) {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE is_read = 0");
    $stmt->execute();
    $stmt->bind_result($unread_count);
    $stmt->fetch();
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MCC MEMO GEN</title>
    <link rel="stylesheet" href="admin_style.css">
    <style>
        /* ===== BASE STYLES ===== */
        * {
            box-sizing: border-box;
        }
        body {
            margin: 0;
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #f4f4f4;
            overflow-x: hidden;
        }

        /* ===== SIDEBAR DESKTOP ===== */
        .sidebar {
            width: 230px;
            background: #1976D2;
            color: #fff;
            position: fixed;
            top: 0; left: 0; bottom: 0;
            padding-top: 24px;
            box-shadow: 2px 0 12px rgba(0,0,0,0.1);
            display: flex;
            flex-direction: column;
            z-index: 1000;
            transition: transform 0.3s ease, width 0.2s ease;
        }

        .sidebar.collapsed {
            width: 60px;
        }

        /* ===== LOGO ===== */
        .sidebar-logo {
            display: flex;
            align-items: center;
            padding: 0 20px 24px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            min-height: 60px;
        }
        .sidebar.collapsed .sidebar-logo span {
            display: none;
        }
        .sidebar-logo svg {
            width: 38px; height: 38px; margin-right: 12px;
        }
        .sidebar.collapsed .sidebar-logo svg {
            margin-right: 0;
        }
        .sidebar-logo span {
            font-size: 1.22rem;
            font-weight: bold;
            letter-spacing: 1px;
        }

        /* ===== NAVIGATION ===== */
        .sidebar-nav {
            flex: 1;
            padding: 24px 0 0;
        }
        .sidebar-nav a {
            display: flex;
            align-items: center;
            gap: 12px;
            color: #e0e0e0;
            text-decoration: none;
            padding: 12px 28px;
            font-size: 1.06rem;
            transition: background 0.2s, color 0.2s;
            white-space: nowrap;
        }
        .sidebar.collapsed .sidebar-nav a span {
            display: none;
        }
        .sidebar.collapsed .sidebar-nav a {
            justify-content: center;
            padding: 16px 0;
        }
        .sidebar-nav a.active,
        .sidebar-nav a:hover {
            background: rgba(255,255,255,0.15);
            color: #fff;
        }

        /* ===== ACTIONS (PROFILE, LOGOUT) ===== */
        .sidebar-actions {
            padding: 20px;
            border-top: 1px solid rgba(255,255,255,0.1);
        }
        .sidebar.collapsed .sidebar-actions {
            padding: 16px 8px;
        }
        .sidebar .btn {
            display: block;
            background: rgba(255,255,255,0.15);
            color: #fff;
            padding: 10px;
            border-radius: 6px;
            text-align: center;
            font-size: 1rem;
            margin-bottom: 10px;
            text-decoration: none;
            font-weight: 600;
            transition: background 0.2s;
        }
        .sidebar .btn:hover {
            background: rgba(255,255,255,0.25);
        }
        .sidebar-user {
            color: #bbb;
            font-size: 0.95rem;
            margin: 12px 0 8px;
            text-align: center;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .sidebar.collapsed .sidebar-user {
            display: none;
        }
        .sidebar-logout-btn {
            background: #e53935;
            color: #fff;
            border: none;
            border-radius: 6px;
            padding: 10px;
            width: 100%;
            font-size: 0.97rem;
            cursor: pointer;
            font-weight: 600;
            transition: background 0.2s;
        }
        .sidebar-logout-btn:hover {
            background: #d32f2f;
        }
        .sidebar.collapsed .sidebar-logout-btn {
            padding: 12px 0;
            font-size: 0;
        }
        .sidebar.collapsed .sidebar-logout-btn::after {
            content: "🔒";
            font-size: 1.3rem;
        }

        /* ===== TOGGLE BUTTON ===== */
        .sidebar-toggle {
            position: absolute;
            top: 18px;
            right: -16px;
            width: 32px;
            height: 32px;
            background: #659ee9;
            color: #fff;
            border-radius: 50%;
            border: none;
            cursor: pointer;
            z-index: 1001;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 1px 2px 6px rgba(0,0,0,0.2);
            transition: all 0.2s;
        }
        .sidebar-toggle:hover {
            background: #588fd6;
        }

        /* ===== MAIN CONTENT ===== */
        .main-content {
            margin-left: 230px;
            padding: 24px;
            transition: margin-left 0.3s ease;
        }
        .sidebar.collapsed ~ .main-content {
            margin-left: 60px;
        }

        /* ===== MOBILE MENU BUTTON (TOP LEFT) ===== */
        .mobile-menu-btn {
            display: none;
            position: fixed;
            top: 16px;
            left: 16px;
            z-index: 1002;
            background: #1976D2;
            color: white;
            border: none;
            width: 44px;
            height: 44px;
            border-radius: 8px;
            font-size: 1.5rem;
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }

        /* ===== MOBILE RESPONSIVE ===== */
        @media (max-width: 800px) {
            .mobile-menu-btn {
                display: block;
            }

            .sidebar {
                transform: translateX(-100%);
                width: 260px;
                position: fixed;
                height: 100vh;
                top: 0;
                left: 0;
                box-shadow: 4px 0 20px rgba(0,0,0,0.2);
            }

            .sidebar.active {
                transform: translateX(0);
            }

            .sidebar.collapsed {
                transform: translateX(-100%);
            }

            .sidebar-toggle {
                display: none;
            }

            .main-content {
                margin-left: 0;
                padding: 20px 16px 20px 16px;
            }

            /* Prevent body scroll when sidebar open */
            body.sidebar-open {
                overflow: hidden;
            }

            .sidebar-overlay {
                display: none;
                position: fixed;
                top: 0; left: 0; right: 0; bottom: 0;
                background: rgba(0,0,0,0.5);
                z-index: 999;
            }
            body.sidebar-open .sidebar-overlay {
                display: block;
            }
        }

        /* Icon styles */
        .sidebar-icon {
            width: 1.5em;
            height: 1.5em;
            display: inline-block;
            vertical-align: middle;
            text-align: center;
        }
    </style>
</head>
<body>

<!-- Mobile Sidebar Overlay -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- Mobile Menu Button -->
<button class="mobile-menu-btn" id="mobileMenuBtn">☰</button>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
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

    <div class="sidebar-nav">
        <a href="dashboard" class="<?= $current_page == 'dashboard' ? 'active' : '' ?>">
            <span class="sidebar-icon">📊</span><span>Dashboard</span>
        </a>

        <?php if (current_user_can('manage_users')): ?>
        <a href="users" class="<?= $current_page == 'users' ? 'active' : '' ?>">
            <span class="sidebar-icon">👥</span><span>Users</span>
        </a>
        <?php endif; ?>

        <?php if (current_user_can('view_memo')): ?>
        <a href="memos" class="<?= $current_page == 'memos' ? 'active' : '' ?>">
            <span class="sidebar-icon">📄</span><span>Memorandums</span>
        </a>
        <?php endif; ?>

        <?php if (current_user_can('upload_header')): ?>
        <a href="upload_memo_header" class="<?= $current_page == 'upload_memo_header' ? 'active' : '' ?>">
            <span class="sidebar-icon">🖼️</span><span>Upload Header</span>
        </a>
        <?php endif; ?>

        <?php if (current_user_can('add_department')): ?>
        <a href="department" class="<?= $current_page == 'department' ? 'active' : '' ?>">
            <span class="sidebar-icon">🏢</span><span>Department</span>
        </a>
        <?php endif; ?>
    </div>

    <div class="sidebar-actions">
        <a href="profile" class="btn">👤</a>
        <?php if (current_user_can('can_create_memo')): ?>
            <a href="memo_add" class="btn">+ Add Memos</a>
        <?php endif; ?>
        <div class="sidebar-user">
            <?= htmlspecialchars($_SESSION['user_fullname'] ?? $_SESSION['admin_name'] ?? 'User') ?>
        </div>
        <form action="../logout" method="post">
            <button class="sidebar-logout-btn" type="submit">Logout</button>
        </form>
    </div>
</div>

<div class="main-content">
    <!-- Your page content will go here -->
</div>

<script>
    // DOM Elements
    const sidebar = document.getElementById('sidebar');
    const mobileMenuBtn = document.getElementById('mobileMenuBtn');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    const body = document.body;

    // Toggle sidebar on mobile
    mobileMenuBtn?.addEventListener('click', () => {
        sidebar.classList.add('active');
        body.classList.add('sidebar-open');
    });

    // Close sidebar when clicking overlay or outside
    sidebarOverlay?.addEventListener('click', () => {
        sidebar.classList.remove('active');
        body.classList.remove('sidebar-open');
    });

    // Desktop toggle (only visible on desktop)
    const sidebarToggle = document.createElement('button');
    sidebarToggle.className = 'sidebar-toggle';
    sidebarToggle.innerHTML = '<span id="toggleIcon">☰</span>';
    sidebarToggle.title = 'Toggle Sidebar';
    sidebar.appendChild(sidebarToggle);

    const toggleIcon = document.getElementById('toggleIcon');
    sidebarToggle.onclick = function(e) {
        e.stopPropagation();
        sidebar.classList.toggle('collapsed');
        if (sidebar.classList.contains('collapsed')) {
            toggleIcon.textContent = '▶';
        } else {
            toggleIcon.textContent = '☰';
        }
        localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
    };

    // Restore sidebar state on load (desktop only)
    window.addEventListener('DOMContentLoaded', () => {
        if (window.innerWidth > 800) {
            if (localStorage.getItem('sidebarCollapsed') === 'true') {
                sidebar.classList.add('collapsed');
                toggleIcon.textContent = '▶';
            }
        }
    });
</script>

</body>
</html>