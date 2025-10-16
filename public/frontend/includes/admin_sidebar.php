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

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MCC MEMO GEN</title>
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
            padding: 1rem 2rem;
            border-bottom: 1px solid #dee2e6;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .content-body {
            padding: 2rem;
        }

        /* Mobile Styles */
        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: fixed;
                top: 0;
                left: 0;
                transform: translateY(-100%);
                transition: transform 0.3s ease;
                z-index: 1050;
            }

            .sidebar.mobile-open {
                transform: translateY(0);
            }

            .sidebar-header {
                padding: 0.75rem 1rem;
            }

            .sidebar-nav {
                max-height: 60vh;
                overflow-y: auto;
            }

            .nav-item {
                margin: 0.2rem 0.5rem;
            }

            .main-content {
                margin-left: 0 !important;
                padding-top: var(--header-height);
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
            }

            .content-body {
                padding: 1rem;
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
    </style>
</head>
<body>
    <!-- Mobile Header -->
    <div class="mobile-header d-lg-none">
        <button class="mobile-menu-btn" id="mobileMenuBtn">
            <i class="bi bi-list"></i>
        </button>
        <h6 class="mobile-title">MemoGen</h6>
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
                <a href="dashboard" class="nav-link <?= $current_page == 'dashboard' ? 'active' : '' ?>">
                    <i class="nav-icon bi bi-speedometer2"></i>
                    <span class="nav-text">Dashboard</span>
                </a>
            </div>

            <?php if (current_user_can('manage_users')): ?>
            <div class="nav-item">
                <a href="users" class="nav-link <?= $current_page == 'users' ? 'active' : '' ?>">
                    <i class="nav-icon bi bi-people"></i>
                    <span class="nav-text">Users</span>
                </a>
            </div>
            <?php endif; ?>

            <?php if (current_user_can('view_memo')): ?>
            <div class="nav-item">
                <a href="memos" class="nav-link <?= $current_page == 'memos' ? 'active' : '' ?>">
                    <i class="nav-icon bi bi-file-earmark-text"></i>
                    <span class="nav-text">Memorandums</span>
                </a>
            </div>
            <?php endif; ?>

            <?php if (current_user_can('upload_header')): ?>
            <div class="nav-item">
                <a href="upload_memo_header" class="nav-link <?= $current_page == 'upload_memo_header' ? 'active' : '' ?>">
                    <i class="nav-icon bi bi-upload"></i>
                    <span class="nav-text">Upload Header</span>
                </a>
            </div>
            <?php endif; ?>

            <?php if (current_user_can('add_department')): ?>
            <div class="nav-item">
                <a href="department" class="nav-link <?= $current_page == 'department' ? 'active' : '' ?>">
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
                <a href="profile" class="btn-sidebar">
                    <span>Profile Settings</span>
                </a>
                
                <?php if (current_user_can('can_create_memo')): ?>
                <a href="memo_add" class="btn-sidebar">
                    <span>+ Create Memo</span>
                </a>
                <?php endif; ?>

                <form action="../logout" method="post" class="d-inline">
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
            <h4 class="mb-0"><?= ucfirst(str_replace('_', ' ', pathinfo($current_page, PATHINFO_FILENAME))) ?></h4>
        </div>
        <div class="content-body">
            <!-- Your main content goes here -->
            <div class="container-fluid">
                <!-- Page content will be loaded here -->
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
            });

            // Close sidebar when clicking backdrop
            sidebarBackdrop.on('click', function() {
                sidebar.removeClass('mobile-open');
                sidebarBackdrop.removeClass('show');
            });

            // Close sidebar when clicking nav links on mobile
            $('.nav-link').on('click', function() {
                if ($(window).width() <= 768) {
                    sidebar.removeClass('mobile-open');
                    sidebarBackdrop.removeClass('show');
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

            // Smooth animations
            $('.nav-link').hover(
                function() {
                    $(this).css('transform', 'translateX(5px)');
                },
                function() {
                    if (!$(this).hasClass('active')) {
                        $(this).css('transform', 'translateX(0)');
                    }
                }
            );
        });
    </script>
</body>
</html>