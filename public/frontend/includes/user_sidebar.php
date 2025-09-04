<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/db.php';
$current_page = basename($_SERVER['PHP_SELF']);
$unread = 0;
if (isset($_SESSION['user_id'])) {
    $uid = intval($_SESSION['user_id']);
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>MemoGen User Panel</title>
    <link rel="stylesheet" href="/frontend/includes/user_style.css">
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f4f4f4;
        }
        .sidebar {
            width: 220px;
            background: #1976d2;
            color: #fff;
            position: fixed;
            top: 0; left: 0; bottom: 0;
            padding-top: 24px;
            box-shadow: 2px 0 8px rgba(0,0,0,0.08);
            display: flex;
            flex-direction: column;
            z-index: 100;
            transition: width 0.2s;
        }
        .sidebar.collapsed {
            width: 60px;
        }
        .sidebar-logo {
            display: flex;
            align-items: center;
            padding: 0 20px 24px 20px;
            border-bottom: 1px solid #1565c0;
            min-height: 60px;
        }
        .sidebar-logo span {
            font-size: 1.15rem;
            font-weight: bold;
            letter-spacing: 1px;
        }
        .sidebar-logo svg {
            width: 38px; height: 38px; margin-right: 10px;
        }
        .sidebar.collapsed .sidebar-logo span {
            display: none;
        }
        .sidebar.collapsed .sidebar-logo svg {
            margin-right: 0;
        }
        .sidebar-nav {
            flex: 1;
            padding: 24px 0 0 0;
        }
        .sidebar-nav a {
            display: flex;
            align-items: center;
            gap: 12px;
            color: #e3eafc;
            text-decoration: none;
            padding: 12px 28px;
            font-size: 1.06rem;
            transition: background 0.15s, color 0.15s;
            white-space: nowrap;
            position: relative;
        }
        .sidebar-icon {
            width: 1.5em;
            height: 1.5em;
            display: inline-block;
            vertical-align: middle;
            text-align: center;
            font-size: 1.4em;
        }
        .sidebar.collapsed .sidebar-nav a span:not(.sidebar-icon) {
            display: none;
        }
        .sidebar.collapsed .sidebar-nav a {
            justify-content: center;
            padding: 12px 0;
        }
        .sidebar-nav a.active, .sidebar-nav a:hover {
            background: #1565c0;
            color: #fff;
        }
        .badge {
            background: #e53935;
            color: #fff;
            border-radius: 50%;
            font-size: 0.80rem;
            padding: 2px 7px;
            margin-left: 6px;
            display: inline-block;
        }
        .sidebar.collapsed .badge {
            position: absolute;
            top: 9px;
            right: 16px;
            margin-left: 0;
        }
        .sidebar-actions {
            padding: 20px 20px 12px 20px;
            border-top: 1px solid #1565c0;
            transition: padding 0.2s;
        }
        .sidebar.collapsed .sidebar-actions {
            padding-left: 8px;
            padding-right: 8px;
        }
        .sidebar-user {
            color: #e3eafc;
            font-size: 0.97rem;
            margin-bottom: 8px;
            white-space: nowrap;
            text-align: center;
        }
        .sidebar.collapsed .sidebar-user {
            display: none;
        }
        .sidebar-logout-btn {
            background: #e53935;
            color: #fff;
            border: none;
            border-radius: 5px;
            padding: 8px 0;
            width: 100%;
            font-size: 0.97rem;
            cursor: pointer;
            margin-top: 4px;
            font-weight: bold;
            transition: font-size 0.2s;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .sidebar.collapsed .sidebar-logout-btn {
            font-size: 0;
        }
        .sidebar.collapsed .sidebar-logout-btn:after {
            content: "\1F511";
            font-size: 1.2rem;
            color: #fff;
        }
        .sidebar-toggle {
            position: absolute;
            top: 18px;
            right: -16px;
            width: 32px;
            height: 32px;
            background: #1565c0;
            color: #fff;
            border-radius: 50%;
            border: none;
            cursor: pointer;
            z-index: 101;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 1px 1px 6px rgba(0,0,0,0.08);
            transition: right 0.2s, background 0.18s;
        }
        .sidebar.collapsed .sidebar-toggle {
            right: -16px;
        }
        .main-content {
            margin-left: 220px;
            padding: 32px 32px 32px 32px;
            transition: margin-left 0.2s;
        }
        .sidebar.collapsed ~ .main-content {
            margin-left: 60px;
        }
        @media (max-width: 800px) {
            .sidebar { width: 100%; height: auto; position: static; flex-direction: row; }
            .sidebar.collapsed { width: 100%; }
            .main-content { margin-left: 0; padding: 16px; }
        }
    </style>
</head>
<body>
<div class="sidebar" id="sidebar">
    <button class="sidebar-toggle" id="sidebarToggle" title="Toggle Sidebar">
        <span id="toggleIcon">&#9776;</span>
    </button>
    <div class="sidebar-logo">
        <!-- Unique SVG logo for user (example: person & document, blue palette) -->
        <svg viewBox="0 0 38 38" fill="none">
            <circle cx="19" cy="19" r="19" fill="#1565c0"/>
            <ellipse cx="19" cy="15" rx="6" ry="6" fill="#fff"/>
            <ellipse cx="19" cy="28" rx="10" ry="6" fill="#fff" opacity="0.85"/>
            <rect x="26" y="11" width="7" height="13" rx="2" fill="#42a5f5" stroke="#fff" stroke-width="1"/>
            <rect x="28" y="14" width="3" height="1.5" rx="0.5" fill="#fff"/>
            <rect x="28" y="17" width="3" height="1.5" rx="0.5" fill="#fff"/>
        </svg>
        <span>MemoGen User</span>
    </div>
    <div class="sidebar-nav">
        <a href="dashboard.php" class="<?= $current_page == 'dashboard.php' ? 'active' : '' ?>">
            <span class="sidebar-icon">&#127968;</span>
            <span>Dashboard</span>
        </a>
        <a href="memos.php" class="<?= $current_page == 'memos.php' ? 'active' : '' ?>">
            <span class="sidebar-icon">&#128196;</span>
            <span>My Memos</span>
        </a>
        
    </div>
    <div class="sidebar-actions">
        <a href="profile.php" class="<?= $current_page == 'profile.php' ? 'active' : '' ?>">
            <span class="sidebar-icon">&#128100;</span>
            <span>Profile</span>
        </a>
        <div class="sidebar-user">
            <?php if (isset($_SESSION['fullname'])) echo htmlspecialchars($_SESSION['fullname']); ?>
        </div>
        <form action="/frontend/logout.php" method="post" style="margin:0;">
            <button class="sidebar-logout-btn" type="submit">Logout</button>
        </form>
    </div>
</div>
<div class="main-content">
<!-- Your main content starts here -->
<script>
    // Collapsible sidebar JS
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const toggleIcon = document.getElementById('toggleIcon');
    sidebarToggle.onclick = function() {
        sidebar.classList.toggle('collapsed');
        if(sidebar.classList.contains('collapsed')) {
            toggleIcon.innerHTML = '&#9654;'; // ▶
        } else {
            toggleIcon.innerHTML = '&#9776;'; // ☰
        }
    };
    // Optionally, remember state in localStorage
    window.addEventListener('DOMContentLoaded', function() {
        if(localStorage.getItem('sidebarCollapsedUser') === 'true') {
            sidebar.classList.add('collapsed');
            toggleIcon.innerHTML = '&#9654;';
        }
    });
    sidebarToggle.addEventListener('click', function() {
        localStorage.setItem('sidebarCollapsedUser', sidebar.classList.contains('collapsed'));
    });
</script>