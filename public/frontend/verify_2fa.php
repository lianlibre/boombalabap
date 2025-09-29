<?php
session_start();

// === DEBUG: Enable for troubleshooting ===
// error_log("Session ID: " . session_id());
// error_log("SESSION: " . print_r($_SESSION, true));
// error_log("POST: " . print_r($_POST, true));

// Check if 2FA session exists
if (!isset($_SESSION['2fa_otp']) || !isset($_SESSION['2fa_email'])) {
    die("<script>alert('No active session. Please log in again.'); window.location.href='login';</script>");
}

// Check expiration
$expires_at = strtotime($_SESSION['2fa_otp_expires']);
$is_expired = time() > $expires_at;

$error = '';
$success = false;
$email = $_SESSION['2fa_email'];

// === Handle Resend OTP ===
if (isset($_POST['resend_otp'])) {
require_once 'includes/phpmailer/class.phpmailer.php';
require_once 'includes/phpmailer/class.smtp.php';
require_once 'includes/phpmailer/PHPMailerAutoload.php';
require_once 'includes/header.php';
require_once 'includes/idps.php';
   

    // Generate new OTP
    $new_otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
    $new_expires = date("Y-m-d H:i:s", strtotime('+10 minutes'));

    // Update session
    $_SESSION['2fa_otp'] = $new_otp;
    $_SESSION['2fa_otp_expires'] = $new_expires;

    // Send email
    $mail = new PHPMailer(true);
    try {
        ob_start(); // Prevent output

        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'mccmemogen@gmail.com'; // Your email
        $mail->Password   = 'ftfk gtsf rvvh jpkh';  // App password
        $mail->SMTPSecure = 'tls';
        $mail->Port       = 587;

        $mail->setFrom('noreply@mccmemo.com', 'MCC Memo System');
        $mail->addAddress($email, $_SESSION['2fa_fullname'] ?? 'User');

        $mail->isHTML(true);
        $mail->Subject = 'Your 2FA Code - MCC Memo Generator';
        $mail->Body = "
            <h2>Two-Factor Authentication</h2>
            <p>Hello <strong>{$_SESSION['2fa_fullname']}</strong>,</p>
            <p>You requested a new verification code:</p>
            <div style='font-size:28px; font-weight:bold; letter-spacing:8px; background:#f0f8ff; padding:20px; border-radius:10px; color:#1976d2; display:inline-block;'>
                $new_otp
            </div>
            <p>This code expires in 10 minutes.</p>
            <small>If you didn’t request this, contact admin.</small>
        ";

        $mail->send();
        ob_end_clean();

        // Set success message
        $error = ''; // Clear previous errors
        $resend_success = true;
    } catch (Exception $e) {
        ob_end_clean();
        error_log("Resend OTP Failed: " . $mail->ErrorInfo);
        $error = "Failed to send new code. Please try again later.";
    }
}

// === Handle OTP Verification ===
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['otp'])) {
    $entered_otp = trim($_POST['otp']);

    if (empty($entered_otp)) {
        $error = "Please enter the 6-digit code.";
    } elseif (!preg_match('/^\d{6}$/', $entered_otp)) {
        $error = "Code must be exactly 6 digits.";
    } else {
        $current_otp = $_SESSION['2fa_otp'];
        $expires_at = strtotime($_SESSION['2fa_otp_expires']);

        if (time() > $expires_at) {
            $error = "Code has expired. Please request a new one.";
        } elseif ($entered_otp === $current_otp) {
            // ✅ SUCCESSFUL VERIFICATION

            $role = $_SESSION['temp_role'];

            // Promote temp user data to permanent session
            foreach ($_SESSION as $key => $value) {
                if (strpos($key, 'temp_user_') === 0) {
                    $_SESSION[str_replace('temp_user_', 'user_', $key)] = $value;
                    unset($_SESSION[$key]);
                }
            }

            $_SESSION['role'] = $role;
            $_SESSION['permissions'] = $_SESSION['temp_permissions'];
            $_SESSION['2fa_verified'] = true;
            $_SESSION['last_login_time'] = time();

            // Cleanup 2FA & temp keys
            unset(
                $_SESSION['2fa_otp'],
                $_SESSION['2fa_otp_expires'],
                $_SESSION['2fa_email'],
                $_SESSION['2fa_fullname'],
                $_SESSION['temp_role'],
                $_SESSION['temp_permissions']
            );

            // Remove any remaining temp_user_* keys
            foreach (array_keys($_SESSION) as $key) {
                if (substr($key, 0, 10) === 'temp_user_') {
                    unset($_SESSION[$key]);
                }
            }

            // Define redirect URL
            $ADMIN_PANEL_ACCESS_ROLES = [
                'admin',
                'dept_head_bsit', 'dept_head_bsba', 'dept_head_bshm',
                'dept_head_bsed', 'dept_head_beed',
                'library', 'soa', 'guidance', 'school_counselor'
            ];

            $redirect_url = in_array($role, $ADMIN_PANEL_ACCESS_ROLES)
                ? "admin/dashboard"
                : "user/dashboard";

            $success = true;
        } else {
            $error = "Invalid code. Please try again.";
            error_log("OTP Mismatch: got '$entered_otp' vs '{$current_otp}'");
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Verify 2FA - MCC Memo Generator</title>

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
            display: flex;
            justify-content: center;
            align-items: flex-start;
            padding: 20px 0;
        }

        #particles {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            pointer-events: none;
        }

        .container {
            max-width: 460px;
            width: 90%;
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

        input {
            padding: 14px;
            margin-bottom: 16px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 15px;
            width: 100%;
            transition: all 0.3s ease;
            font-family: 'Poppins', sans-serif;
            text-align: center;
            letter-spacing: 8px;
            font-size: 1.5rem;
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

        .btn:hover:not(:disabled) {
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

        /* Countdown Timer */
        .countdown-timer {
            font-size: 14px;
            color: #d32f2f;
            margin-top: -10px;
            margin-bottom: 16px;
            font-weight: 600;
            text-align: left;
        }
    </style>
</head>
<body>

    <!-- Background Particles -->
    <canvas id="particles"></canvas>

    <!-- 2FA Verification Form -->
    <div class="container">
        <div class="header-banner">
            <img src="assets/mcc_nobg.png" alt="MCC Logo" class="logo-image" />
        </div>
        <h1 class="logo-text">MCC MEMO GEN</h1>
        <p class="tagline">Enter 2FA Code</p>

        <form method="post" id="otpForm">
            <label>Verification Code <span>(sent to <?= htmlspecialchars($email) ?>)</span></label>
            <input 
                type="text" 
                name="otp" 
                maxlength="6" 
                required 
                autocomplete="off" 
                placeholder="123456"
                <?= $is_expired ? 'disabled' : '' ?>
            />

            <?php if ($is_expired): ?>
                <span class="countdown-timer">Code expired. Request a new one.</span>
            <?php endif; ?>

            <button type="submit" class="btn">
                <i class="fas fa-shield-alt"></i> Verify & Continue
            </button>

            <button type="submit" name="resend_otp" class="btn secondary" style="margin-top:10px;">
                <i class="fas fa-redo"></i> Resend Code
            </button>

            <div class="form-footer">
                <a href="login"><i class="fas fa-arrow-left"></i> Back to Login</a>
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
        document.querySelector('input[name="otp"]').addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '').slice(0, 6);
        });
    </script>

    <!-- Show Alerts -->
    <script>
        <?php if ($error): ?>
        Swal.fire({
            icon: 'error',
            title: 'Invalid Code',
            text: <?= json_encode($error) ?>,
            confirmButtonColor: '#1976d2'
        });
        <?php endif; ?>

        <?php if (isset($resend_success)): ?>
        Swal.fire({
            icon: 'success',
            title: 'Code Sent!',
            text: 'A new verification code has been sent to your email.',
            confirmButtonColor: '#1976d2'
        });
        <?php endif; ?>

        <?php if ($success): ?>
        Swal.fire({
            icon: 'success',
            title: 'Authenticated!',
            text: 'Redirecting to dashboard...',
            confirmButtonColor: '#1976d2',
            timer: 1500,
            timerProgressBar: true
        }).then(() => {
            window.location.href = <?= json_encode($redirect_url) ?>;
        });
        <?php endif; ?>
    </script>
</body>
</html>