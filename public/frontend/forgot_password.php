<?php
session_start();
require_once "includes/db.php";
include 'includes/recaptcha.php';
require_once 'includes/header.php';
require_once 'includes/idps.php';
// Include PHPMailer classes manually (adjust path if needed)
require_once 'includes/phpmailer/class.phpmailer.php';
require_once 'includes/phpmailer/class.smtp.php';
require_once 'includes/phpmailer/PHPMailerAutoload.php';

$error = "";
$success = false;

// Helper: Escape output
function e($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

// Only process POST
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST['email'] ?? '');

    if (empty($email)) {
        $error = "Email is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        $domain = strtolower(substr(strrchr($email, "@"), 1));
        $allowed_domains = ['mcclawis.edu.ph', 'outlook.com', 'office365.com', 'microsoft.com', 'gmail.com'];

        if (!in_array($domain, $allowed_domains)) {
            $error = "Use @mcclawis.edu.ph, @gmail.com, or MS365 email.";
        } else {
            try {
                // Check if user exists
                $stmt = $conn->prepare("SELECT id, fullname FROM users WHERE email = ?");
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $result = $stmt->get_result();
                $user = $result->fetch_assoc();

                if (!$user) {
                    $error = "No account found with that email.";
                } else {
                    $fullname = $user['fullname'];

                    // Generate 6-digit OTP
                    $otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
                    $expires_at = date("Y-m-d H:i:s", strtotime('+15 minutes')); // 15 min expiry

                    // Delete old OTPs for this email
                    $delete_stmt = $conn->prepare("DELETE FROM password_resets WHERE email = ?");
                    $delete_stmt->bind_param("s", $email);
                    $delete_stmt->execute();

                    // Insert new OTP
                    $insert_stmt = $conn->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
                    $insert_stmt->bind_param("sss", $email, $otp, $expires_at);
                    $insert_stmt->execute();

                    if ($insert_stmt->affected_rows > 0) {
                        // Send OTP via Email
                        $mail = new PHPMailer(true);

                        try {
                            // SMTP Configuration
                            $mail->isSMTP();
                            $mail->Host       = 'smtp.gmail.com'; // Change if needed
                            $mail->SMTPAuth   = true;
                            $mail->Username   = 'mccmemogen@gmail.com'; // Your email
                            $mail->Password   = 'ftfk gtsf rvvh jpkh';  // App password
                            $mail->SMTPSecure = 'tls';
                            $mail->Port       = 587;

                            // Recipients
                            $mail->setFrom('noreply@mccmemo.com', 'MCC Memo System');
                            $mail->addAddress($email, $fullname);

                            // Email Content
                            $mail->isHTML(true);
                            $mail->Subject = 'Your Password Reset OTP - MCC Memo';
                            $mail->Body = "
                                <h2>Password Reset OTP</h2>
                                <p>Hello <strong>{$fullname}</strong>,</p>
                                <p>Your one-time verification code is:</p>
                                <div style='font-size:24px; font-weight:bold; letter-spacing:4px; color:#1976d2; background:#f0f8ff; padding:15px; border-radius:8px; display:inline-block;'>
                                    {$otp}
                                </div>
                                <p>This code will expire in 15 minutes.</p>
                                <p>If you didn't request this, please ignore this email.</p>
                                <br>
                                <small>Powered by MCC Memo Generator System</small>
                            ";

                            $mail->send();
                            $_SESSION['reset_email'] = $email; // Mark as OTP sent
                            $success = true; // Trigger SweetAlert success
                        } catch (Exception $e) {
                            error_log("Mail Error: " . $mail->ErrorInfo);
                            $error = "Failed to send OTP. Please try again.";
                        }
                    } else {
                        $error = "An error occurred while generating OTP.";
                    }
                }
            } catch (Exception $e) {
                error_log("Forgot Password DB Error: " . $e->getMessage());
                $error = "An unexpected error occurred. Please try again.";
            }
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
    <title>Forgot Password - MCC Memo Generator</title>

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
            overflow-y: auto; /* ✅ Enable vertical scroll */
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

        .btn:hover:not(:disabled) {
            background: #0d47a1;
        }

        .btn:disabled {
            background: #9eaecb;
            cursor: not-allowed;
            transform: none;
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

    <!-- Forgot Password Form -->
    <div class="container">
        <div class="header-banner">
            <img src="assets/mcc_nobg.png" alt="MCC Logo" class="logo-image" />
        </div>
        <h1 class="logo-text">MCC MEMO GEN</h1>
        <p class="tagline">Official Memo Generator System</p>

        <form method="post" id="forgotPasswordForm">
            <label>Email: <span>(@mcclawis.edu.ph, @gmail.com, or MS365)</span></label>
            <input 
                type="email" 
                name="email" 
                value="<?= e($_POST['email'] ?? '') ?>" 
                required 
                autocomplete="email" 
                placeholder="Enter your email" 
            />

            <button type="submit" class="btn" id="submitBtn">
                <i class="fas fa-paper-plane"></i> Send OTP
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

        // Show alerts after submission
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
            title: 'OTP Sent!',
            html: 'A 6-digit code has been sent to <strong><?= e($email) ?></strong>.<br>Please check your inbox (and spam folder).',
            confirmButtonColor: '#1976d2',
            timer: 2500,
            timerProgressBar: true
        }).then(() => {
            window.location.href = 'reset_password?email=<?= urlencode($email) ?>&otp_sent=1';
        });
        <?php endif; ?>

        // Prevent double-submit & show loading
        document.getElementById('forgotPasswordForm').addEventListener('submit', function (e) {
            const btn = document.getElementById('submitBtn');
            if (btn.disabled) {
                e.preventDefault();
                return;
            }
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
        });
    </script>

</body>
</html>