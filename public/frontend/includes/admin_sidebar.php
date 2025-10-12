<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$current_page = basename($_SERVER['PHP_SELF']);

require_once __DIR__ . "/db.php";
require_once __DIR__ . "/permissions.php";

// Only define function once (though unused, kept as-is)
if (!function_exists('render_admin_sidebar')) {
    function render_admin_sidebar() {
        global $conn;
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>MCC MEMO GEN</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        * {
            box-sizing: border-box;
        }
        body {
            margin: 0;
            font-family: 'Segoe UI', system-ui, sans-serif;
            background: #f8fafc;
            color: #1e293b;
        }

        /* Sidebar Base */
        .sidebar {
            width: 240px;
            background: linear-gradient(180deg, #1e40af, #1d4ed8);
            color: white;
            position: fixed;
            top: 0; left: 0; bottom: 0;
            display: flex;
            flex-direction: column;
            z-index: 100;
            transition: transform 0.3s ease, width 0.2s ease;
            box-shadow: 4px 0 12px rgba(0,0,0,0.1);
        }

        /* Collapsed on Desktop */
        .sidebar.collapsed {
            width: 72px;
        }

        /* Mobile Overlay Behavior */
        @media (max-width: 800px) {
            .sidebar {
                transform: translateX(-100%);
                width: 260px;
                height: 100vh;
            }
            .sidebar.mobile-open {
                transform: translateX(0);
            }
        }

        /* Logo */
        .sidebar-logo {
            display: flex;
            align-items: center;
            padding: 0 20px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.15);
            min-height: 70px;
        }
        .sidebar-logo svg {
            width: 36px; height: 36px; margin-right: 14px;
            flex-shrink: 0;
        }
        .sidebar-logo span {
            font-size: 1.3rem;
            font-weight: 700;
            letter-spacing: -0.5px;
            white-space: nowrap;
        }
        .sidebar.collapsed .sidebar-logo span,
        .sidebar.mobile-collapsed .sidebar-logo span {
            display: none;
        }
        .sidebar.collapsed .sidebar-logo svg {
            margin-right: 0;
        }

        /* Nav */
        .sidebar-nav {
            flex: 1;
            padding: 16px 0;
            overflow-y: auto;
        }
        .sidebar-nav a {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 12px 24px;
            color: rgba(255,255,255,0.85);
            text-decoration: none;
            font-size: 1.02rem;
            transition: all 0.2s;
            white-space: nowrap;
        }
        .sidebar.collapsed .sidebar-nav a span,
        .sidebar.mobile-collapsed .sidebar-nav a span {
            display: none;
        }
        .sidebar.collapsed .sidebar-nav a,
        .sidebar.mobile-collapsed .sidebar-nav a {
            justify-content: center;
            padding: 14px;
        }
        .sidebar-nav a:hover,
        .sidebar-nav a.active {
            background: rgba(255,255,255,0.15);
            color: white;
            border-left: 3px solid #60a5fa;
        }
        .sidebar.collapsed .sidebar-nav a:hover,
        .sidebar.collapsed .sidebar-nav a.active {
            border-left: none;
            background: rgba(255,255,255,0.2);
        }

        /* Actions (Profile, Logout, etc.) */
        .sidebar-actions {
            padding: 16px 20px;
            border-top: 1px solid rgba(255,255,255,0.1);
        }
        .sidebar .btn {
            display: block;
            background: rgba(255,255,255,0.15);
            color: white;
            padding: 10px;
            border-radius: 8px;
            text-align: center;
            font-weight: 600;
            text-decoration: none;
            margin-bottom: 12px;
            transition: background 0.2s;
        }
        .sidebar .btn:hover {
            background: rgba(255,255,255,0.25);
        }
        .sidebar.collapsed .btn span,
        .sidebar.mobile-collapsed .btn span {
            display: none;
        }
        .sidebar.collapsed .btn::before,
        .sidebar.mobile-collapsed .btn::before {
            content: "+";
            display: block;
        }
        .sidebar-user {
            color: rgba(255,255,255,0.7);
            font-size: 0.92rem;
            text-align: center;
            padding: 8px 0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .sidebar.collapsed .sidebar-user,
        .sidebar.mobile-collapsed .sidebar-user {
            display: none;
        }
        .sidebar-logout-btn {
            background: #dc2626;
            color: white;
            border: none;
            border-radius: 8px;
            padding: 10px;
            width: 100%;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }
        .sidebar-logout-btn:hover {
            background: #b91c1c;
        }
        .sidebar.collapsed .sidebar-logout-btn,
        .sidebar.mobile-collapsed .sidebar-logout-btn {
            padding: 12px;
            font-size: 0;
        }
        .sidebar.collapsed .sidebar-logout-btn::after,
        .sidebar.mobile-collapsed .sidebar-logout-btn::after {
            content: "🚪";
            font-size: 1.3rem;
        }

        /* Toggle Button */
        .sidebar-toggle {
            position: absolute;
            top: 20px;
            right: -36px;
            width: 32px;
            height: 32px;
            background: #1d4ed8;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            z-index: 101;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 6px rgba(0,0,0,0.15);
            font-size: 14px;
        }
        @media (max-width: 800px) {
            .sidebar-toggle {
                right: 16px;
                top: 16px;
                background: white;
                color: #1d4ed8;
                z-index: 102;
            }
        }

        /* Main Content */
        .main-content {
            margin-left: 240px;
            padding: 32px;
            transition: margin-left 0.2s;
        }
        .sidebar.collapsed ~ .main-content {
            margin-left: 72px;
        }
        @media (max-width: 800px) {
            .main-content {
                margin-left: 0;
                padding: 20px 16px;
            }
        }

        /* Mobile Backdrop */
        .mobile-backdrop {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 99;
        }
        .mobile-backdrop.active {
            display: block;
        }

        /* Icons */
        .sidebar-icon {
            font-size: 1.2rem;
            width: 24px;
            text-align: center;
        }
    </style>
</head>
<body>

    <!-- Mobile Backdrop -->
    <div class="mobile-backdrop" id="mobileBackdrop"></div>

    <div class="sidebar" id="sidebar">
        <button class="sidebar-toggle" id="sidebarToggle" title="Toggle Menu">
            <span id="toggleIcon">☰</span>
        </button>

        <div class="sidebar-logo">
            <svg viewBox="0 0 38 38" fill="none">
                <circle cx="19" cy="19" r="19" fill="#3b82f6"/>
                <ellipse cx="19" cy="15" rx="6" ry="6" fill="#fff"/>
                <ellipse cx="19" cy="28" rx="10" ry="6" fill="#e0f2fe"/>
                <rect x="26" y="11" width="7" height="13" rx="2" fill="#93c5fd" stroke="#fff" stroke-width="1"/>
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
            <a href="profile" class="btn">
                <span>👤 Profile</span>
            </a>
            <?php if (current_user_can('can_create_memo')): ?>
                <a href="memo_add" class="btn">
                    <span>+ Add Memos</span>
                </a>
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
        <!-- Your page content goes here -->
        <h1>Welcome to Admin Panel</h1>
        <p>Your main content will appear here.</p>
    </div>

    <script>
        const sidebar = document.getElementById('sidebar');
        const toggleBtn = document.getElementById('sidebarToggle');
        const backdrop = document.getElementById('mobileBackdrop');
        const toggleIcon = document.getElementById('toggleIcon');

        function isMobile() {
            return window.innerWidth <= 800;
        }

        function closeMobileSidebar() {
            if (isMobile()) {
                sidebar.classList.remove('mobile-open');
                backdrop.classList.remove('active');
            }
        }

        toggleBtn.addEventListener('click', () => {
            if (isMobile()) {
                sidebar.classList.toggle('mobile-open');
                backdrop.classList.toggle('active');
            } else {
                sidebar.classList.toggle('collapsed');
                const isCollapsed = sidebar.classList.contains('collapsed');
                localStorage.setItem('sidebarCollapsed', isCollapsed);
                toggleIcon.textContent = isCollapsed ? '▶' : '☰';
            }
        });

        backdrop.addEventListener('click', closeMobileSidebar);

        // Close sidebar when clicking nav links (mobile only)
        document.querySelectorAll('.sidebar-nav a').forEach(link => {
            link.addEventListener('click', closeMobileSidebar);
        });

        // On load: restore desktop state
        window.addEventListener('DOMContentLoaded', () => {
            if (!isMobile() && localStorage.getItem('sidebarCollapsed') === 'true') {
                sidebar.classList.add('collapsed');
                toggleIcon.textContent = '▶';
            }
        });

        // Handle resize
        window.addEventListener('resize', () => {
            if (!isMobile()) {
                sidebar.classList.remove('mobile-open');
                backdrop.classList.remove('active');
            }
        });
    </script>
</body>
</html>