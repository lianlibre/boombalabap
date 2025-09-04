<?php
session_start();
require_once "includes/db.php";

$error = "";
$email_error = "";
$login_success = false;
$redirect_url = null;

// Define allowed roles that can access /admin/
$ADMIN_PANEL_ACCESS_ROLES = [
    'admin',
    'dept_head_bsit', 'dept_head_bsba', 'dept_head_bshm',
    'dept_head_bsed', 'dept_head_beed',
    'library', 'soa', 'guidance', 'school_counselor'
];

// Helper: Escape output
function e($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

// Only process login on POST
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"] ?? '');
    $password = $_POST["password"] ?? '';

    if (empty($email)) {
        $error = "Email is required.";
    } elseif (empty($password)) {
        $error = "Password is required.";
    } else {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address.";
        } else {
            $domain = strtolower(substr(strrchr($email, "@"), 1));
            $allowed_domains = ['mcclawis.edu.ph', 'outlook.com', 'office365.com', 'microsoft.com', 'gmail.com'];

            if (!in_array($domain, $allowed_domains)) {
                $email_error = "Please use @mcclawis.edu.ph, @gmail.com, or MS365 email for login.";
            } else {
                try {
                    $stmt = $conn->prepare("SELECT id, fullname, email, department, role, password FROM users WHERE email = ?");
                    $stmt->bind_param("s", $email);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $user = $result->fetch_assoc();

                    if ($user && password_verify($password, $user['password'])) {
                        unset($user['password']);
                        foreach ($user as $key => $value) {
                            $_SESSION['user_' . $key] = $value;
                        }
                        $_SESSION['role'] = $user['role'];

                        $perm_stmt = $conn->prepare("SELECT * FROM roles_permissions WHERE role = ?");
                        $perm_stmt->bind_param("s", $user['role']);
                        $perm_stmt->execute();
                        $perm_result = $perm_stmt->get_result();
                        $permissions = $perm_result->fetch_assoc();

                        if (!$permissions) {
                            $permissions = [
                                'can_create_memo' => 0,
                                'can_view_memo' => 1,
                                'can_upload_header' => 0,
                                'can_manage_users' => 0,
                                'can_add_department' => 0,
                                'can_edit_profile' => 1,
                                'can_access_dashboard' => 1
                            ];
                        }
                        $_SESSION['permissions'] = $permissions;

                        if (in_array($user['role'], $ADMIN_PANEL_ACCESS_ROLES)) {
                            $redirect_url = "admin/dashboard.php";
                        } else {
                            $redirect_url = "user/dashboard.php";
                        }

                        $login_success = true;
                    } else {
                        $error = "Invalid email or password.";
                    }
                } catch (Exception $e) {
                    error_log("Login DB Error: " . $e->getMessage());
                    $error = "An error occurred during login. Please try again.";
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Login - MCC Memo Generator</title>

    <!-- Google Font: Poppins (Modern & Classy) -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600;700&display=swap" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />

    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        .header-banner {
        width: 100%;
        height: 80px;
        display: flex;
        justify-content: center;
        align-items: center;
        border-radius: 16px 16px 0 0;
        margin-bottom: 20px;
        overflow: hidden;
        position: relative;
        }

        .logo-image {
            width: 100px;
            height: auto;
            max-height: 60px;
            border: 2px solid white;
            border-radius: 4px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.2);
        }
        .password-container {
        position: relative;
        width: 100%;
        }

        .password-container input {
            padding-right: 50px;
            width: 100%;
        }

        .password-container .password-toggle {
            position: absolute;
            top: 50%;
            right: 16px;
            transform: translateY(-50%);
            cursor: pointer;
            color: #aaa;
            transition: color 0.3s ease;
            pointer-events: auto;
        }

        .password-container .password-toggle:hover {
            color: #1976d2;
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: #f5f7fa;
            color: #333;
            overflow: hidden;
            position: relative;
        }

        /* Background Canvas */
        #particles {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
        }

        /* Main Container */
        .container {
        max-width: 460px;
        width: 90%;
        margin: 100px auto;
        padding: 20px 35px 40px; /* Reduced top padding */
        background: white;
        border-radius: 16px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1), 0 5px 10px rgba(0, 0, 0, 0.05);
        text-align: center;
        z-index: 10;
        }

        /* Animated Logo Text */
        .logo-text {
            font-size: 2.6rem;
            font-weight: 700;
            color: #1976d2;
            letter-spacing: 1.5px;
            margin-bottom: 10px;
            position: relative;
            display: inline-block;
        }

        .logo-text::after {
            content: '';
            position: absolute;
            bottom: -8px;
            left: 50%;
            transform: translateX(-50%);
            width: 70%;
            height: 3px;
            background: linear-gradient(90deg, #1976d2, #4fc3f7);
            border-radius: 2px;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 0.6; }
            50% { opacity: 1; width: 85%; }
        }

        .tagline {
            font-size: 1rem;
            color: #666;
            margin-bottom: 30px;
            font-weight: 500;
        }

        /* Form Styles */
        form {
            display: flex;
            flex-direction: column;
        }

        label {
            text-align: left;
            font-weight: 600;
            color: #444;
            margin: 14px 0 6px;
            font-size: 14px;
        }

        label span {
            color: #777;
            font-weight: normal;
            font-size: 12px;
        }

        input {
            padding: 14px;
            margin-bottom: 16px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 15px;
            width: 100%;
            transition: all 0.3s ease;
            font-family: 'Poppins', sans-serif;
        }

        input:focus {
            outline: none;
            border-color: #1976d2;
            box-shadow: 0 0 0 3px rgba(25, 118, 210, 0.15);
        }

        .btn {
            padding: 14px;
            background: #1976d2;
            color: white;
            font-weight: 600;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.3s ease;
            font-family: 'Poppins', sans-serif;
        }

        .btn:hover {
            background: #0d47a1;
            transform: translateY(-2px);
        }

        .btn.secondary {
            background: #f1f1f1;
            color: #333;
            border: 1px solid #ddd;
             text-decoration: none;
        }

        .btn.secondary:hover {
            background: #e0e0e0;
        }

        .btn-container {
            display: flex;
            justify-content: space-between;
            margin-top: 10px;
            gap: 12px;
            flex-wrap: wrap;
        }

        .form-footer {
            margin-top: 24px;
            font-size: 14px;
            color: #777;
        }

        .form-footer a {
            color: #1976d2;
            text-decoration: none;
            font-weight: 500;
        }

        .form-footer a:hover {
            text-decoration: underline;
        }

        /* Responsive */
        @media (max-width: 480px) {
            .container {
                margin: 60px auto;
                padding: 30px 20px;
            }
            .logo-text {
                font-size: 2.2rem;
            }
            .btn-container {
                flex-direction: column;
            }
            .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>

    <!-- Background Particles -->
    <canvas id="particles"></canvas>

    <!-- Login Form -->
    <div class="container">
        <div class="header-banner">
    <img src="assets/mcc_nobg.png" alt="MCC Logo" class="logo-image" />
    </div>
        <h1 class="logo-text">MCC MEMO GEN</h1>
        <p class="tagline">Official Memo Generator System</p>

        <form method="post" id="loginForm">
            <label>Email: <span>(@mcclawis.edu.ph, @gmail.com, or MS365)</span></label>
            <input type="email" name="email" value="<?= e($_POST['email'] ?? '') ?>" required autocomplete="email" placeholder="Enter your email" />

            <label>Password:</label>
            <div class="password-container">
                <input type="password" name="password" id="password" required autocomplete="current-password" placeholder="Enter your password" />
                <i class="fas fa-eye-slash password-toggle" id="togglePassword"></i>
            </div>

            <div class="btn-container">
                <button type="submit" class="btn">
                    <i class="fas fa-sign-in-alt"></i> Login
                </button>
                <a href="register.php" class="btn secondary">
                    <i class="fas fa-user-plus"></i> Register
                </a>
            </div>

            <div class="form-footer">
                <a href="forgot_password.php">Forgot password?</a>
            </div>
        </form>
    </div>

    <!-- Interactive Particle Script -->
    <script>
        const canvas = document.getElementById('particles');
        const ctx = canvas.getContext('2d');
        let width = canvas.width = window.innerWidth;
        let height = canvas.height = window.innerHeight;

        let mouse = { x: width / 2, y: height / 2 };

        window.addEventListener('mousemove', (e) => {
            mouse.x = e.clientX;
            mouse.y = e.clientY;
        });

        window.addEventListener('touchmove', (e) => {
            e.preventDefault();
            mouse.x = e.touches[0].clientX;
            mouse.y = e.touches[0].clientY;
        }, { passive: false });

        window.addEventListener('resize', () => {
            width = canvas.width = window.innerWidth;
            height = canvas.height = window.innerHeight;
            init();
        });

        const particleCount = 70;
        const particles = [];
        const colors = ['#1976d2', '#4fc3f7', '#bbdefb', '#e3f2fd', '#0d47a1'];

        class Particle {
            constructor() {
                this.x = Math.random() * width;
                this.y = Math.random() * height;
                this.size = Math.random() * 2 + 0.5;
                this.baseX = this.x;
                this.baseY = this.y;
                this.density = (Math.random() * 20) + 10;
            }

            draw() {
                ctx.beginPath();
                ctx.arc(this.x, this.y, this.size, 0, Math.PI * 2);
                ctx.fillStyle = '#1976d2';
                ctx.globalAlpha = 0.3;
                ctx.fill();
                ctx.globalAlpha = 1;
            }

            update() {
                const dx = mouse.x - this.x;
                const dy = mouse.y - this.y;
                const distance = Math.sqrt(dx * dx + dy * dy);
                const maxDistance = 100;
                let force = (maxDistance - distance) / maxDistance;
                force = force < 0 ? 0 : force;

                if (distance < maxDistance) {
                    const dirX = dx / distance * force * this.density;
                    const dirY = dy / distance * force * this.density;
                    this.x -= dirX;
                    this.y -= dirY;
                }

                const returnX = (this.baseX - this.x) * 0.05;
                const returnY = (this.baseY - this.y) * 0.05;
                this.x += returnX;
                this.y += returnY;
            }
        }

        function init() {
            particles.length = 0;
            for (let i = 0; i < particleCount; i++) {
                particles.push(new Particle());
            }
        }

        function animate() {
            ctx.clearRect(0, 0, width, height);
            for (let i = 0; i < particles.length; i++) {
                particles[i].update();
                particles[i].draw();
            }
            requestAnimationFrame(animate);
        }

        init();
        animate();

        // SweetAlert Notifications
        <?php if ($error): ?>
        Swal.fire({
            icon: 'error',
            title: 'Login Failed',
            text: <?= json_encode($error) ?>,
            confirmButtonColor: '#1976d2'
        });
        <?php endif; ?>

        <?php if ($email_error): ?>
        Swal.fire({
            icon: 'warning',
            title: 'Email Domain Required',
            text: <?= json_encode($email_error) ?>,
            confirmButtonColor: '#1976d2'
        });
        <?php endif; ?>

        <?php if ($login_success): ?>
        Swal.fire({
            icon: 'success',
            title: 'Logged In!',
            text: 'Welcome, <?= e(addslashes($_SESSION['user_fullname'])) ?>!',
            confirmButtonColor: '#1976d2',
            timer: 1500,
            timerProgressBar: true
        }).then(() => {
            const allowedPaths = ['admin/dashboard.php', 'user/dashboard.php'];
            const redirect = <?= json_encode($redirect_url) ?>;
            if (allowedPaths.includes(redirect)) {
                window.location.href = redirect;
            } else {
                window.location.href = 'user/dashboard.php';
            }
        });
        <?php endif; ?>



        // Toggle Password Visibility
const passwordInput = document.getElementById('password');
const togglePassword = document.getElementById('togglePassword');

togglePassword.addEventListener('click', function () {
    const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
    passwordInput.setAttribute('type', type);

    // Toggle the eye icon
    this.classList.toggle('fa-eye');
    this.classList.toggle('fa-eye-slash');
});
    </script>
</body>
</html>