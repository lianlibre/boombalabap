<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$current_page = basename($_SERVER['PHP_SELF']);

// Example notification count logic (adjust as in your system)
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
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        /* Sidebar Styles */
        .sidebar {
            width: 250px;
            background: linear-gradient(180deg, #1976d2 0%, #0d47a1 100%);
            color: #fff;
            position: fixed;
            top: 0; 
            left: 0; 
            bottom: 0;
            padding-top: 20px;
            box-shadow: 2px 0 12px rgba(0,0,0,0.1);
            display: flex;
            flex-direction: column;
            z-index: 1000;
            transition: all 0.3s ease;
        }
        
        .sidebar.collapsed {
            width: 70px;
        }
        
        .sidebar-logo {
            display: flex;
            align-items: center;
            padding: 0 20px 20px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            min-height: 60px;
        }
        
        .sidebar.collapsed .sidebar-logo span {
            display: none;
        }
        
        .sidebar-logo svg {
            width: 36px; 
            height: 36px; 
            margin-right: 14px;
            flex-shrink: 0;
        }
        
        .sidebar.collapsed .sidebar-logo svg {
            margin-right: 0;
        }
        
        .sidebar-logo span {
            font-size: 1.3rem;
            font-weight: 700;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }
        
        .sidebar-nav {
            flex: 1;
            padding: 20px 0;
            overflow-y: auto;
        }
        
        .sidebar-nav a {
            display: flex;
            align-items: center;
            gap: 14px;
            color: rgba(255,255,255,0.85);
            text-decoration: none;
            padding: 12px 24px;
            font-size: 1.05rem;
            transition: all 0.2s ease;
            white-space: nowrap;
            border-left: 3px solid transparent;
        }
        
        .sidebar.collapsed .sidebar-nav a span {
            display: none;
        }
        
        .sidebar.collapsed .sidebar-nav a {
            justify-content: center;
            padding: 14px 0;
            gap: 0;
        }
        
        .sidebar-nav a.active, 
        .sidebar-nav a:hover {
            background: rgba(255,255,255,0.15);
            color: #fff;
            border-left: 3px solid #4fc3f7;
        }
        
        .sidebar-actions {
            padding: 20px;
            border-top: 1px solid rgba(255,255,255,0.1);
            transition: padding 0.3s;
        }
        
        .sidebar.collapsed .sidebar-actions {
            padding: 20px 8px;
        }
        
        .notification-bell-wrapper {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 16px;
        }
        
        .notification-bell {
            position: relative;
            display: inline-block;
            text-decoration: none;
        }
        
        .notification-bell svg {
            width: 28px;
            height: 28px;
            fill: #4fc3f7;
        }
        
        .notification-badge {
            position: absolute;
            top: -6px;
            right: -6px;
            background: #ff5252;
            color: #fff;
            border-radius: 50%;
            font-size: 0.75rem;
            padding: 2px 6px;
            min-width: 18px;
            text-align: center;
            font-weight: bold;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        
        .sidebar .btn {
            display: block;
            background: rgba(255,255,255,0.15);
            color: #fff;
            padding: 10px;
            border-radius: 6px;
            text-align: center;
            font-size: 1rem;
            margin-bottom: 12px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.2s;
            border: 1px solid rgba(255,255,255,0.2);
        }
        
        .sidebar .btn:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-2px);
        }
        
        .sidebar.collapsed .sidebar .btn {
            padding: 12px 0;
            font-size: 0;
        }
        
        .sidebar.collapsed .sidebar .btn::after {
            content: "+";
            font-size: 1.5rem;
            display: block;
        }
        
        .sidebar-user {
            color: rgba(255,255,255,0.8);
            font-size: 0.95rem;
            margin: 12px 0;
            text-align: center;
            white-space: nowrap;
            padding: 8px 12px;
            background: rgba(0,0,0,0.1);
            border-radius: 6px;
            font-weight: 500;
        }
        
        .sidebar.collapsed .sidebar-user {
            display: none;
        }
        
        .sidebar-logout-btn {
            background: #ff5252;
            color: #fff;
            border: none;
            border-radius: 6px;
            padding: 10px;
            width: 100%;
            font-size: 1rem;
            cursor: pointer;
            margin-top: 8px;
            font-weight: 600;
            transition: all 0.2s;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        
        .sidebar-logout-btn:hover {
            background: #ff1744;
            transform: translateY(-2px);
        }
        
        .sidebar.collapsed .sidebar-logout-btn {
            padding: 12px 0;
            font-size: 0;
        }
        
        .sidebar.collapsed .sidebar-logout-btn::after {
            content: "🚪";
            font-size: 1.4rem;
            display: block;
        }
        
        .sidebar-toggle {
            position: absolute;
            top: 18px;
            right: -18px;
            width: 36px;
            height: 36px;
            background: #4fc3f7;
            color: #0d47a1;
            border-radius: 50%;
            border: none;
            cursor: pointer;
            z-index: 1001;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
            transition: all 0.3s;
            font-weight: bold;
        }
        
        .sidebar.collapsed .sidebar-toggle {
            right: -18px;
            transform: rotate(180deg);
        }
        
        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 250px;
            padding: 24px;
            transition: all 0.3s ease;
            min-height: calc(100vh - 48px);
        }
        
        .sidebar.collapsed ~ .main-content {
            margin-left: 70px;
        }
        
        /* Responsive Design */
        @media (max-width: 992px) {
            .sidebar {
                width: 70px;
            }
            
            .sidebar.collapsed {
                width: 250px;
            }
            
            .main-content {
                margin-left: 70px;
            }
            
            .sidebar.collapsed ~ .main-content {
                margin-left: 250px;
            }
            
            .sidebar-toggle {
                right: -18px;
            }
            
            .sidebar.collapsed .sidebar-toggle {
                right: -18px;
                transform: rotate(0deg);
            }
        }
        
        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: auto;
                flex-direction: row;
                padding: 0;
                z-index: 1000;
            }
            
            .sidebar.collapsed {
                width: 100%;
                height: 100vh;
                flex-direction: column;
            }
            
            .sidebar-logo {
                min-height: 50px;
                padding: 0 15px;
            }
            
            .sidebar-logo svg {
                width: 30px;
                height: 30px;
            }
            
            .sidebar-nav {
                display: none;
                flex-direction: column;
                width: 100%;
                padding: 10px 0;
            }
            
            .sidebar.collapsed .sidebar-nav {
                display: flex;
            }
            
            .sidebar-nav a {
                padding: 12px 20px;
                font-size: 1rem;
            }
            
            .sidebar-actions {
                display: none;
                padding: 15px;
                width: 100%;
            }
            
            .sidebar.collapsed .sidebar-actions {
                display: flex;
                flex-direction: column;
            }
            
            .sidebar-user {
                margin: 10px 0;
                font-size: 0.9rem;
            }
            
            .sidebar-logout-btn {
                padding: 8px;
                font-size: 0.95rem;
            }
            
            .sidebar-toggle {
                top: 8px;
                right: 10px;
                width: 32px;
                height: 32px;
                background: #4fc3f7;
            }
            
            .main-content {
                margin-left: 0;
                padding: 16px;
                margin-top: 60px;
            }
            
            .sidebar.collapsed ~ .main-content {
                margin-left: 0;
                margin-top: 60px;
            }
        }
        
        @media (max-width: 480px) {
            .sidebar-logo span {
                font-size: 1.1rem;
            }
            
            .sidebar-nav a {
                font-size: 0.95rem;
                padding: 10px 15px;
            }
            
            .main-content {
                padding: 12px;
                margin-top: 55px;
            }
        }
        
        /* Scrollbar styling */
        .sidebar-nav::-webkit-scrollbar {
            width: 6px;
        }
        
        .sidebar-nav::-webkit-scrollbar-track {
            background: rgba(0,0,0,0.1);
        }
        
        .sidebar-nav::-webkit-scrollbar-thumb {
            background: rgba(255,255,255,0.3);
            border-radius: 3px;
        }
        
        .sidebar-nav::-webkit-scrollbar-thumb:hover {
            background: rgba(255,255,255,0.5);
        }
    </style>
</head>
<body>
    
<div class="sidebar" id="sidebar">
    <button class="sidebar-toggle" id="sidebarToggle" title="Toggle Sidebar">
        <span id="toggleIcon">☰</span>
    </button>
    <div class="sidebar-logo">
        <svg viewBox="0 0 38 38" fill="none">
            <circle cx="19" cy="19" r="19" fill="#4fc3f7"/>
            <ellipse cx="19" cy="15" rx="6" ry="6" fill="#fff"/>
            <ellipse cx="19" cy="28" rx="10" ry="6" fill="#fff" opacity="0.85"/>
            <rect x="26" y="11" width="7" height="13" rx="2" fill="#1976d2" stroke="#fff" stroke-width="1"/>
            <rect x="28" y="14" width="3" height="1.5" rx="0.5" fill="#fff"/>
            <rect x="28" y="17" width="3" height="1.5" rx="0.5" fill="#fff"/>
        </svg>
        <span>MemoGen</span>
    </div>
    
    <div class="sidebar-nav">
        <a href="dashboard" class="<?= $current_page == 'dashboard.php' || $current_page == 'dashboard' ? 'active' : '' ?>">
            <span class="sidebar-icon">📊</span><span>Dashboard</span>
        </a>

        <?php if (current_user_can('manage_users')): ?>
        <a href="users" class="<?= $current_page == 'users.php' || $current_page == 'users' ? 'active' : '' ?>">
            <span class="sidebar-icon">👥</span><span>Users</span>
        </a>
        <?php endif; ?>

        <?php if (current_user_can('view_memo')): ?>
        <a href="memos" class="<?= $current_page == 'memos.php' || $current_page == 'memos' ? 'active' : '' ?>">
            <span class="sidebar-icon">📝</span><span>Memorandums</span>
        </a>
        <?php endif; ?>

        <?php if (current_user_can('upload_header')): ?>
        <a href="upload_memo_header" class="<?= $current_page == 'upload_memo_header.php' || $current_page == 'upload_memo_header' ? 'active' : '' ?>">
            <span class="sidebar-icon">📤</span><span>Upload Header</span>
        </a>
        <?php endif; ?>

        <?php if (current_user_can('add_department')): ?>
        <a href="department" class="<?= $current_page == 'department.php' || $current_page == 'department' ? 'active' : '' ?>">
            <span class="sidebar-icon">🏢</span><span>Department</span>
        </a>
        <?php endif; ?>
    </div>

    <div class="sidebar-actions">
        <div class="notification-bell-wrapper">
            <a href="memos" class="notification-bell" title="Notifications">
                <svg viewBox="0 0 24 24">
                    <path d="M12 24c1.3 0 2.4-1 2.5-2.3h-5c.1 1.3 1.2 2.3 2.5 2.3zm6.3-6V11c0-3.1-2-5.8-5-6.6V4a1.3 1.3 0 1 0-2.6 0v.4c-3 .8-5 3.5-5 6.6v7L3 20v1h18v-1l-2.7-2zM19 20H5v-.2l2.8-2.8V11c0-2.9 2.1-5.2 5.2-5.2s5.2 2.3 5.2 5.2v6l2.8 2.8V20z"/>
                </svg>
                <?php if ($unread_count > 0): ?>
                    <span class="notification-badge"><?= $unread_count ?></span>
                <?php endif; ?>
            </a>
        </div>
        <a href="profile" class="btn">Profile</a>
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
    <!-- Your main content goes here -->
</div>

<script>
    // Sidebar toggle functionality
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const toggleIcon = document.getElementById('toggleIcon');
    
    // Check screen size on load
    function checkScreenSize() {
        if (window.innerWidth <= 768) {
            sidebar.classList.remove('collapsed');
            toggleIcon.textContent = '☰';
            localStorage.removeItem('sidebarCollapsed');
        } else if (window.innerWidth <= 992) {
            sidebar.classList.add('collapsed');
            toggleIcon.textContent = '☰';
            localStorage.setItem('sidebarCollapsed', 'true');
        }
    }
    
    // Toggle sidebar
    sidebarToggle.addEventListener('click', function() {
        const isCollapsed = sidebar.classList.toggle('collapsed');
        
        if (window.innerWidth > 768) {
            toggleIcon.textContent = isCollapsed ? '▶' : '☰';
            localStorage.setItem('sidebarCollapsed', isCollapsed);
        } else {
            toggleIcon.textContent = '☰';
            localStorage.removeItem('sidebarCollapsed');
        }
    });
    
    // Initialize on load
    window.addEventListener('DOMContentLoaded', function() {
        checkScreenSize();
        
        // Load saved state for desktop
        if (window.innerWidth > 768) {
            const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
            if (isCollapsed) {
                sidebar.classList.add('collapsed');
                toggleIcon.textContent = '▶';
            }
        }
    });
    
    // Handle resize
    window.addEventListener('resize', checkScreenSize);
</script>
</body>
</html>