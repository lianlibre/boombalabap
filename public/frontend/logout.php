<?php
// logout.php
session_start();
session_unset();
session_destroy();

// Show SweetAlert and redirect after confirmation
echo <<<HTML
<!DOCTYPE html>
<html>
<head>
    <title>Logged Out - MemoGen</title>
    <link rel="stylesheet" href="/frontend/includes/user_style.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
<script>
Swal.fire({
    icon: 'success',
    title: 'Logout Successful',
    text: 'You have been logged out of MemoGen.',
    confirmButtonColor: '#1976d2'
}).then(function() {
    window.location.href = "login.php";
});
</script>
</body>
</html>
HTML;
exit;