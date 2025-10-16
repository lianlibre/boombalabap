<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$current_page = basename($_SERVER['PHP_SELF']);

// Example notification count logic (adjust as in your system)
require_once __DIR__ . "/db.php";
require_once __DIR__ . "/permissions.php";

// Only define function once
if (!function_exists('render_admin_sidebar')) {
    function render_admin_sidebar() {
        global $conn; // Use existing DB connection
    }
}
$unread_count = 0;
if (isset($_SESSION['admin_id'])) {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE is_read = 0");
    $stmt->execute();
    $stmt->bind_result($unread_count);
    $stmt->fetch();
    $stmt->close();
}

// Get page title based on current page
$page_titles = [
    'dashboard' => 'Dashboard',
    'users' => 'User Management',
    'memos' => 'Memorandums',
    'upload_memo_header' => 'Upload Memo Header',
    'department' => 'Department Management',
    'profile' => 'Profile Settings',
    'memo_add' => 'Create New Memo'
];
$page_title = $page_titles[pathinfo($current_page, PATHINFO_FILENAME)] ?? 'MemoGen System';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MCC MEMO GEN - <?= $page_title ?></title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <!-- Custom CSS -->
    <style>
        :root {
            --primary-color: #1976D2;
            --secondary-color: #1565c0;
            --accent-color: #42a5f5;
            --danger-color: #e53935;
            --text-light: #cfcfcf;
            --sidebar-width: 250px;
            --sidebar-collapsed-width: 70px;
            --header-height: 60px;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
            overflow-x: hidden;
        }

        /* Sidebar Styles */
        .sidebar {
            width: var(--sidebar-width);
            background: linear-gradient(180deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            z-index: 1000;
            transition: all 0.3s ease;
            box-shadow: 2px 0 15px rgba(0,0,0,0.1);
        }

        .sidebar.collapsed {
            width: var(--sidebar-collapsed-width);
        }

        .sidebar-header {
            padding: 1rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            height: var(--header-height);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .logo-container {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .logo-icon {
            width: 35px;
            height: 35px;
            background: white;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary-color);
            font-weight: bold;
            font-size: 1.2rem;
        }

        .logo-text {
            font-size: 1.3rem;
            font-weight: 700;
            color: white;
            transition: opacity 0.3s ease;
        }

        .sidebar.collapsed .logo-text {
            opacity: 0;
            width: 0;
        }

        .toggle-btn {
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .toggle-btn:hover {
            background: rgba(255,255,255,0.3);
        }

        .sidebar-nav {
            padding: 1rem 0;
            flex: 1;
            overflow-y: auto;
        }

        .nav-item {
            margin: 0.2rem 0.8rem;
        }

        .nav-link {
            color: var(--text-light);
            text-decoration: none;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: all 0.3s ease;
            white-space: nowrap;
        }

        .nav-link:hover, .nav-link.active {
            background: rgba(255,255,255,0.15);
            color: white;
            transform: translateX(5px);
        }

        .nav-icon {
            width: 20px;
            text-align: center;
            font-size: 1.2rem;
        }

        .nav-text {
            transition: opacity 0.3s ease;
            font-weight: 500;
        }

        .sidebar.collapsed .nav-text {
            opacity: 0;
            width: 0;
        }

        .sidebar-footer {
            padding: 1rem;
            border-top: 1px solid rgba(255,255,255,0.1);
            background: rgba(0,0,0,0.1);
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 1rem;
            color: var(--text-light);
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--accent-color);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }

        .user-details {
            flex: 1;
        }

        .user-name {
            font-weight: 600;
            color: white;
            font-size: 0.9rem;
        }

        .user-role {
            font-size: 0.8rem;
            color: var(--text-light);
        }

        .sidebar.collapsed .user-details {
            display: none;
        }

        .action-buttons {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .btn-sidebar {
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            color: white;
            padding: 0.5rem;
            border-radius: 6px;
            text-decoration: none;
            text-align: center;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }

        .btn-sidebar:hover {
            background: rgba(255,255,255,0.2);
            color: white;
        }

        .btn-logout {
            background: var(--danger-color);
            border: none;
        }

        .btn-logout:hover {
            background: #c62828;
        }

        .sidebar.collapsed .btn-sidebar span {
            display: none;
        }

        .sidebar.collapsed .btn-sidebar::after {
            content: "⚙";
        }

        .sidebar.collapsed .btn-logout::after {
            content: "🚪";
        }

        /* Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }

        .sidebar.collapsed ~ .main-content {
            margin-left: var(--sidebar-collapsed-width);
        }

        .content-header {
            background: white;
            padding: 1.5rem 2rem;
            border-bottom: 1px solid #dee2e6;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            margin-bottom: 0;
        }

        .content-body {
            padding: 2rem;
            min-height: calc(100vh - 120px);
        }

        /* Mobile Styles */
        @media (max-width: 768px) {
            .sidebar {
                width: 280px;
                height: 100vh;
                position: fixed;
                top: 0;
                left: -280px;
                transform: translateX(0);
                transition: transform 0.3s ease;
                z-index: 1050;
            }

            .sidebar.mobile-open {
                transform: translateX(280px);
            }

            .sidebar-header {
                padding: 0.75rem 1rem;
            }

            .sidebar-nav {
                max-height: calc(100vh - 200px);
                overflow-y: auto;
            }

            .nav-item {
                margin: 0.2rem 0.5rem;
            }

            .main-content {
                margin-left: 0 !important;
                width: 100%;
            }

            .mobile-header {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                height: var(--header-height);
                background: var(--primary-color);
                z-index: 1040;
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 0 1rem;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            }

            .mobile-menu-btn {
                background: none;
                border: none;
                color: white;
                font-size: 1.5rem;
            }

            .mobile-title {
                color: white;
                font-weight: 600;
                margin: 0;
                font-size: 1.1rem;
            }

            .content-body {
                padding: 1rem;
                margin-top: var(--header-height);
            }

            .content-header {
                display: none;
            }
        }

        /* Notification Badge */
        .notification-badge {
            background: var(--danger-color);
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 0.7rem;
            display: flex;
            align-items: center;
            justify-content: center;
            position: absolute;
            top: -5px;
            right: -5px;
        }

        /* Backdrop for mobile */
        .sidebar-backdrop {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1045;
        }

        .sidebar-backdrop.show {
            display: block;
        }

        /* Smooth transitions */
        * {
            transition: color 0.3s ease, background-color 0.3s ease;
        }

        /* Page specific content styles */
        .page-content {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 2rem;
            margin-bottom: 2rem;
        }
    </style>
</head>
<body>
    <!-- Mobile Header -->
    <div class="mobile-header d-lg-none">
        <button class="mobile-menu-btn" id="mobileMenuBtn">
            <i class="bi bi-list"></i>
        </button>
        <h6 class="mobile-title"><?= $page_title ?></h6>
        <div></div> <!-- Spacer for flex alignment -->
    </div>

    <!-- Backdrop for Mobile -->
    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="logo-container">
                <div class="logo-icon">MG</div>
                <span class="logo-text">MemoGen</span>
            </div>
            <button class="toggle-btn d-none d-lg-block" id="sidebarToggle">
                <i class="bi bi-chevron-left"></i>
            </button>
        </div>

        <div class="sidebar-nav">
            <div class="nav-item">
                <a href="dashboard.php" class="nav-link <?= $current_page == 'dashboard.php' ? 'active' : '' ?>">
                    <i class="nav-icon bi bi-speedometer2"></i>
                    <span class="nav-text">Dashboard</span>
                </a>
            </div>

            <?php if (current_user_can('manage_users')): ?>
            <div class="nav-item">
                <a href="users.php" class="nav-link <?= $current_page == 'users.php' ? 'active' : '' ?>">
                    <i class="nav-icon bi bi-people"></i>
                    <span class="nav-text">Users</span>
                </a>
            </div>
            <?php endif; ?>

            <?php if (current_user_can('view_memo')): ?>
            <div class="nav-item">
                <a href="memos.php" class="nav-link <?= $current_page == 'memos.php' ? 'active' : '' ?>">
                    <i class="nav-icon bi bi-file-earmark-text"></i>
                    <span class="nav-text">Memorandums</span>
                </a>
            </div>
            <?php endif; ?>

            <?php if (current_user_can('upload_header')): ?>
            <div class="nav-item">
                <a href="upload_memo_header.php" class="nav-link <?= $current_page == 'upload_memo_header.php' ? 'active' : '' ?>">
                    <i class="nav-icon bi bi-upload"></i>
                    <span class="nav-text">Upload Header</span>
                </a>
            </div>
            <?php endif; ?>

            <?php if (current_user_can('add_department')): ?>
            <div class="nav-item">
                <a href="department.php" class="nav-link <?= $current_page == 'department.php' ? 'active' : '' ?>">
                    <i class="nav-icon bi bi-building"></i>
                    <span class="nav-text">Department</span>
                </a>
            </div>
            <?php endif; ?>
        </div>

        <div class="sidebar-footer">
            <div class="user-info">
                <div class="user-avatar">
                    <?php 
                    $userName = $_SESSION['user_fullname'] ?? $_SESSION['admin_name'] ?? 'User';
                    echo strtoupper(substr($userName, 0, 1)); 
                    ?>
                </div>
                <div class="user-details">
                    <div class="user-name"><?= htmlspecialchars($userName) ?></div>
                    <div class="user-role">Administrator</div>
                </div>
            </div>

            <div class="action-buttons">
                <a href="profile.php" class="btn-sidebar">
                    <span>Profile Settings</span>
                </a>
                
                <?php if (current_user_can('can_create_memo')): ?>
                <a href="memo_add.php" class="btn-sidebar">
                    <span>+ Create Memo</span>
                </a>
                <?php endif; ?>

                <form action="../logout.php" method="post" class="d-inline">
                    <button type="submit" class="btn-sidebar btn-logout w-100">
                        <span>Logout</span>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <div class="content-header d-none d-lg-block">
            <div class="d-flex justify-content-between align-items-center">
                <h4 class="mb-0 text-dark"><?= $page_title ?></h4>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="dashboard.php" class="text-decoration-none">Home</a></li>
                        <li class="breadcrumb-item active"><?= $page_title ?></li>
                    </ol>
                </nav>
            </div>
        </div>
        <div class="content-body">
            <!-- Dynamic content area - This is where your page content will display -->
            <div class="container-fluid">
                <div class="page-content">
                    <?php 
                    // This is where your existing page content should be included
                    // For example, if this is dashboard.php, the dashboard content goes here
                    // The content from your original pages should be placed in this area
                    ?>
                    
                    <!-- TEMPORARY PLACEHOLDER - REMOVE THIS IN PRODUCTION -->
                    <div class="alert alert-info">
                        <h5>Content Area</h5>
                        <p class="mb-0">This is where your page content will be displayed. Make sure to include your existing page content between the <code>&lt;div class="page-content"&gt;</code> tags.</p>
                    </div>
                    
                    <!-- Your actual page content should replace the placeholder above -->
                    <!-- Example for dashboard.php content: -->
                    <!--
                    <h3>Welcome to Dashboard</h3>
                    <p>Your dashboard content goes here...</p>
                    -->
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap & jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        $(document).ready(function() {
            const sidebar = $('#sidebar');
            const mainContent = $('#mainContent');
            const sidebarToggle = $('#sidebarToggle');
            const mobileMenuBtn = $('#mobileMenuBtn');
            const sidebarBackdrop = $('#sidebarBackdrop');

            // Desktop sidebar toggle
            sidebarToggle.on('click', function() {
                sidebar.toggleClass('collapsed');
                const icon = sidebarToggle.find('i');
                if (sidebar.hasClass('collapsed')) {
                    icon.removeClass('bi-chevron-left').addClass('bi-chevron-right');
                } else {
                    icon.removeClass('bi-chevron-right').addClass('bi-chevron-left');
                }
                localStorage.setItem('sidebarCollapsed', sidebar.hasClass('collapsed'));
            });

            // Mobile menu toggle
            mobileMenuBtn.on('click', function() {
                sidebar.addClass('mobile-open');
                sidebarBackdrop.addClass('show');
                $('body').css('overflow', 'hidden');
            });

            // Close sidebar when clicking backdrop
            sidebarBackdrop.on('click', function() {
                sidebar.removeClass('mobile-open');
                sidebarBackdrop.removeClass('show');
                $('body').css('overflow', 'auto');
            });

            // Close sidebar when clicking nav links on mobile
            $('.nav-link').on('click', function() {
                if ($(window).width() <= 768) {
                    sidebar.removeClass('mobile-open');
                    sidebarBackdrop.removeClass('show');
                    $('body').css('overflow', 'auto');
                }
            });

            // Close sidebar when pressing escape key
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape') {
                    sidebar.removeClass('mobile-open');
                    sidebarBackdrop.removeClass('show');
                    $('body').css('overflow', 'auto');
                }
            });

            // Load saved sidebar state
            if (localStorage.getItem('sidebarCollapsed') === 'true') {
                sidebar.addClass('collapsed');
                sidebarToggle.find('i').removeClass('bi-chevron-left').addClass('bi-chevron-right');
            }

            // Handle window resize
            $(window).on('resize', function() {
                if ($(window).width() > 768) {
                    sidebar.removeClass('mobile-open');
                    sidebarBackdrop.removeClass('show');
                    $('body').css('overflow', 'auto');
                }
            });

            // Add active state to current page
            const currentPage = '<?= $current_page ?>';
            $('.nav-link').each(function() {
                const href = $(this).attr('href');
                if (href === currentPage) {
                    $(this).addClass('active');
                }
            });
        });
    </script>
</body>
</html>