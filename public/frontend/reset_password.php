<?php
session_start();
require_once "includes/db.php";
include 'includes/recaptcha.php';

// Include PHPMailer classes manually (adjust path if needed)
require_once 'includes/phpmailer/class.phpmailer.php';
require_once 'includes/phpmailer/class.smtp.php';
require_once 'includes/phpmailer/PHPMailerAutoload.php';

// Get email from URL
$email = filter_input(INPUT_GET, 'email', FILTER_VALIDATE_EMAIL);
$otp_sent = $_GET['otp_sent'] ?? null;

// Validate access: must come from forgot_password via session
if (!$email || !$otp_sent || !isset($_SESSION['reset_email']) || $_SESSION['reset_email'] !== $email) {
    die("<script>alert('Invalid or expired request.'); window.location.href='forgot_password';</script>");
}

$error = "";
$success = false;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $entered_otp = trim($_POST['otp']);
    $new_password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Validation
    if (empty($entered_otp)) {
        $error = "Please enter the 6-digit OTP.";
    } elseif (!is_numeric($entered_otp) || strlen($entered_otp) != 6) {
        $error = "OTP must be exactly 6 digits.";
    } elseif (empty($new_password)) {
        $error = "New password is required.";
    } elseif (strlen($new_password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } elseif ($new_password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        try {
            // Fetch stored OTP and expiry
            $stmt = $conn->prepare("SELECT token, expires_at FROM password_resets WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();

            if (!$row) {
                $error = "No reset request found. Please start over.";
            } elseif ($row['token'] !== $entered_otp) {
                $error = "Invalid OTP. Please check and try again.";
            } elseif (strtotime($row['expires_at']) < time()) {
                $error = "OTP has expired. Please request a new one.";
            } else {
                // ✅ Valid OTP — proceed to update password using Bcrypt
                $hashed_password = password_hash($new_password, PASSWORD_BCRYPT, ['cost' => 10]);

                if (!$hashed_password) {
                    $error = "An error occurred during password encryption.";
                } else {
                    // Update user's password
                    $update_stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
                    $update_stmt->bind_param("ss", $hashed_password, $email);

                    if ($update_stmt->execute()) {
                        // Delete used OTP entry
                        $delete_stmt = $conn->prepare("DELETE FROM password_resets WHERE email = ?");
                        $delete_stmt->bind_param("s", $email);
                        $delete_stmt->execute();

                        // Clear session
                        unset($_SESSION['reset_email']);

                        $success = true;
                    } else {
                        $error = "Failed to update password. Please try again later.";
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Reset Password DB Error: " . $e->getMessage());
            $error = "An unexpected error occurred. Please try again.";
        }
    }
}
?>
<?php renderRecaptchaScript('login'); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
    <title>Reset Password - MCC Memo Generator</title>

    <!-- Google Font: Poppins -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600;700&display=swap" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />

    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: #f5f7fa;
            color: #333;
            min-height: 100vh;
            position: relative;
            overflow-y: auto;
            overflow-x: hidden;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            padding: 20px 0;
        }

        /* Background Canvas */
        #particles {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            pointer-events: none;
        }

        /* Main Container */
        .container {
            max-width: 460px;
            width: 90%;
            min-width: 300px;
            margin-top: min(10vh, 40px);
            margin-bottom: 40px;
            padding: 30px 35px 40px;
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1), 0 5px 10px rgba(0, 0, 0, 0.05);
            text-align: center;
            z-index: 10;
        }

        .header-banner {
            width: 100%;
            height: 80px;
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 15px;
        }

        .logo-image {
            height: 90px;
            width: auto;
            max-height: 100px;
        }

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

        /* Base Input Style */
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

        /* Password Container with Eye Icon */
        .password-container {
            position: relative;
            width: 100%;
        }

        .password-container input {
            padding-right: 50px; /* Make space for icon */
            width: 100%;
        }

        .password-toggle {
            position: absolute;
            top: 50%;
            right: 16px;
            transform: translateY(-50%);
            cursor: pointer;
            color: #aaa;
            transition: color 0.3s ease;
            font-size: 1.1rem;
            pointer-events: auto;
            z-index: 2;
        }

        .password-toggle:hover {
            color: #1976d2;
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

        /* OTP Input Styling */
        .otp-input {
            font-size: 1.5rem !important;
            text-align: center;
            letter-spacing: 8px;
        }

        /* Smooth scroll behavior */
        html {
            scroll-behavior: smooth;
        }

        @media (max-width: 480px) {
            .container {
                width: 95%;
                padding: 25px 20px;
            }
            .logo-text {
                font-size: 2.2rem;
            }
        }
    </style>
</head>
<body>

    <!-- Background Particles -->
    <canvas id="particles"></canvas>

    <!-- Reset Password Form -->
    <div class="container">
        <div class="header-banner">
            <img src="assets/mcc_nobg.png" alt="MCC Logo" class="logo-image" />
        </div>
        <h1 class="logo-text">MCC MEMO GEN</h1>
        <p class="tagline">Set New Password</p>

        <form method="post" id="resetForm">
            <label>Enter OTP <span>(sent to <?= htmlspecialchars($email) ?>)</span></label>
            <input type="text" name="otp" maxlength="6" class="otp-input" placeholder="123456" required autocomplete="off" />

            <label>New Password:</label>
            <div class="password-container">
                <input type="password" name="password" id="password" placeholder="Enter new password" required minlength="6" />
                <i class="fas fa-eye-slash password-toggle" data-target="password"></i>
            </div>

            <label>Confirm Password:</label>
            <div class="password-container">
                <input type="password" name="confirm_password" id="confirm_password" placeholder="Re-enter new password" required minlength="6" />
                <i class="fas fa-eye-slash password-toggle" data-target="confirm_password"></i>
            </div>

            <button type="submit" class="btn">
                <i class="fas fa-key"></i> Update Password
            </button>

            <div class="form-footer">
                <a href="forgot_password"><i class="fas fa-arrow-left"></i> Back to Forgot Password</a>
            </div>
        </form>
    </div>

    <!-- Interactive Particle Animation -->
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

        // Allow only digits in OTP field
        document.querySelector('input[name="otp"]').addEventListener('input', function (e) {
            this.value = this.value.replace(/[^0-9]/g, '').slice(0, 6);
        });

        // Toggle Password Visibility
        document.querySelectorAll('.password-toggle').forEach(toggle => {
            toggle.addEventListener('click', function () {
                const targetId = this.getAttribute('data-target');
                const input = document.getElementById(targetId);
                const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
                input.setAttribute('type', type);

                // Toggle icon
                this.classList.toggle('fa-eye');
                this.classList.toggle('fa-eye-slash');
            });
        });

        // Show SweetAlert2 messages
        <?php if ($error): ?>
        Swal.fire({
            icon: 'error',
            title: 'Oops!',
            text: <?= json_encode($error) ?>,
            confirmButtonColor: '#1976d2'
        });
        <?php endif; ?>

        <?php if ($success): ?>
        Swal.fire({
            icon: 'success',
            title: 'Password Updated!',
            text: 'Your password has been successfully changed.',
            confirmButtonColor: '#1976d2',
            timer: 1800,
            timerProgressBar: true
        }).then(() => {
            window.location.href = 'login';
        });
        <?php endif; ?>
    </script>
</body>
</html>