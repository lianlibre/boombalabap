<?php
session_start();
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'admin') {
        header("Location: admin/dashboard.php");
    } else {
        header("Location: user/dashboard.php");
    }
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>MemoGen - Memorandum Generator</title>
    <link rel="stylesheet" href="{{url('frontend/includes/user_style.css')}}">
</head>
<body>
<div class="container" style="margin-top:80px;max-width:500px;">
    <h1>Welcome to Memorandum Generator</h1>
    <p style="margin-bottom:2em;">A simple system for managing and generating professional memorandums.</p>
    <a href="{{url('frontend/login.php')}}" class="btn">Get Started</a>
</div>
</body>
</html>