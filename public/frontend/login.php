<?php
session_start();
require_once "includes/db.php";
include 'includes/recaptcha.php';

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

function e($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

// === FAILED ATTEMPT LOCKOUT SYSTEM ===
$MAX_ATTEMPTS_PER_CYCLE = 3;
$LOCKOUT_TIMES = [
    0 => 5 * 60,     // 5 minutes
    1 => 30 * 60,    // 30 minutes
    2 => 60 * 60,    // 1 hour
];
$current_cycle = $_SESSION['login_lockout_cycle'] ?? 0;
$lockout_duration = $LOCKOUT_TIMES[$current_cycle % 3]; // Cycle after 3rd

$is_locked_out = false;
$unlock_timestamp = 0;

if (isset($_SESSION['login_attempts']) && $_SESSION['login_attempts'] >= $MAX_ATTEMPTS_PER_CYCLE) {
    $last_attempt_time = $_SESSION['last_attempt_time'] ?? time();
    $time_left = $last_attempt_time + $lockout_duration - time();

    if ($time_left > 0) {
        $is_locked_out = true;
        $unlock_timestamp = $last_attempt_time + $lockout_duration;
        $minutes = floor($time_left / 60);
        $seconds = $time_left % 60;
        $error = "Too many failed attempts. Try again in {$minutes}m {$seconds}s.";
    } else {
        // Unlock session
        unset($_SESSION['login_attempts'], $_SESSION['last_attempt_time']);
        $is_locked_out = false;
    }
}

// Only process login on POST
if ($_SERVER["REQUEST_METHOD"] === "POST" && !$is_locked_out) {
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
                $email_error = "Use @mcclawis.edu.ph, @gmail.com, or MS365 email.";
            } else {
                try {
                    $stmt = $conn->prepare("SELECT id, fullname, email, department, role, password FROM users WHERE email = ?");
                    $stmt->bind_param("s", $email);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $user = $result->fetch_assoc();

                    if ($user && password_verify($password, $user['password'])) {
                        // ✅ SUCCESS: Reset lockout
                        unset(
                            $_SESSION['login_attempts'],
                            $_SESSION['last_attempt_time'],
                            $_SESSION['login_lockout_cycle']
                        );

                        // Remove password from user data
                        unset($user['password']);

                        // Temporarily store user info for 2FA phase
                        foreach ($user as $key => $value) {
                            $_SESSION['temp_user_' . $key] = $value;
                        }

                        // Save role separately for later use
                        $role = $user['role'];
                        $_SESSION['temp_role'] = $role;

                        // Load permissions (store temporarily)
                        $perm_stmt = $conn->prepare("SELECT * FROM roles_permissions WHERE role = ?");
                        $perm_stmt->bind_param("s", $role);
                        $perm_stmt->execute();
                        $perm_result = $perm_stmt->get_result();
                        $permissions = $perm_result->fetch_assoc() ?: [
                            'can_create_memo' => 0,
                            'can_view_memo' => 1,
                            'can_upload_header' => 0,
                            'can_manage_users' => 0,
                            'can_add_department' => 0,
                            'can_edit_profile' => 1,
                            'can_access_dashboard' => 1
                        ];
                        $_SESSION['temp_permissions'] = $permissions;

                        // Update last login time
                        $update_stmt = $conn->prepare("UPDATE users SET last_checked = NOW() WHERE id = ?");
                        $update_stmt->bind_param("i", $user['id']);
                        $update_stmt->execute();
                        $update_stmt->close();

                        // === GENERATE 6-DIGIT OTP FOR 2FA ===
                        $otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
                        $expires_at = date("Y-m-d H:i:s", strtotime('+10 minutes'));

                        // Store in session for next step
                        $_SESSION['2fa_otp'] = $otp;
                        $_SESSION['2fa_otp_expires'] = $expires_at;
                        $_SESSION['2fa_email'] = $user['email'];
                        $_SESSION['2fa_fullname'] = $user['fullname'];

                        // Optional: Log OTP
                        error_log("2FA OTP for {$user['email']}: $otp");

                        // === SEND OTP VIA EMAIL USING PHPMAILER ===
                        require_once 'includes/phpmailer/class.phpmailer.php';
                        require_once 'includes/phpmailer/class.smtp.php';
                        require_once 'includes/phpmailer/PHPMailerAutoload.php';

                       

                        $mail = new PHPMailer(true);
                        try {
                            $mail->isSMTP();
                            $mail->Host       = 'smtp.gmail.com'; // Change if needed
                            $mail->SMTPAuth   = true;
                            $mail->Username   = 'mccmemogen@gmail.com'; // Replace
                            $mail->Password   = 'ftfk gtsf rvvh jpkh';  // App Password
                            $mail->SMTPSecure = 'tls';
                            $mail->Port       = 587;

                            $mail->setFrom('noreply@mccmemo.com', 'MCC Memo System');
                            $mail->addAddress($_SESSION['2fa_email'], $_SESSION['2fa_fullname']);

                            $mail->isHTML(true);
                            $mail->Subject = 'Your 2FA Code - MCC Memo Generator';
                            $mail->Body = "
                                <h2>Two-Factor Authentication</h2>
                                <p>Hello <strong>{$_SESSION['2fa_fullname']}</strong>,</p>
                                <p>To complete your login, enter the verification code:</p>
                                <div style='font-size:28px; font-weight:bold; letter-spacing:8px; background:#f0f8ff; padding:20px; border-radius:10px; color:#1976d2; display:inline-block;'>
                                    $otp
                                </div>
                                <p>This code expires in 10 minutes.</p>
                                <small>If you didn’t request this, please contact admin immediately.</small>
                            ";

                            $mail->send();
                        } catch (Exception $e) {
                            error_log("2FA Email Failed: " . $mail->ErrorInfo);
                            // Don't block login — just log failure
                        }
// Clean any accidental output
if (ob_get_level()) {
    ob_clean();
}

// Close session to release lock
session_write_close();

// Now redirect
header("Location: verify_2fa");
exit;
                        // ✅ REDIRECT TO 2FA VERIFICATION
                       // header("Location: verify_2fa.php");
                      //  exit;
                    } else {
                        // ❌ FAILED ATTEMPT
                        $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
                        $_SESSION['last_attempt_time'] = time();

                        $attempts_left = $MAX_ATTEMPTS_PER_CYCLE - $_SESSION['login_attempts'];
                        if ($attempts_left > 0) {
                            $error = "Invalid email or password. {$attempts_left} attempt(s) left.";
                        } else {
                            $_SESSION['login_lockout_cycle'] = $current_cycle + 1;
                            $is_locked_out = true;
                            $unlock_timestamp = time() + $lockout_duration;
                            $duration_label = [300 => "5 minutes", 1800 => "30 minutes", 3600 => "1 hour"];
                            $label = $duration_label[$lockout_duration] ?? "a while";
                            $error = "Account locked for {$label}.";
                        }
                    }
                } catch (Exception $e) {
                    error_log("Login DB Error: " . $e->getMessage());
                    $error = "An error occurred. Please try again.";
                }
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
    <title>Login - MCC Memo Generator</title>

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
            transform: translateY(-2px);
        }

        .btn:disabled {
            background: #ccc;
            color: #666;
            cursor: not-allowed;
            transform: none;
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

        /* Password Toggle */
        .password-container {
            position: relative;
            width: 100%;
        }

        .password-container input {
            padding-right: 50px;
        }

        .password-container .password-toggle {
            position: absolute;
            top: 50%;
            right: 16px;
            transform: translateY(-50%);
            cursor: pointer;
            color: #aaa;
            transition: color 0.3s ease;
        }

        .password-container .password-toggle:hover {
            color: #1976d2;
        }

        /* Countdown Timer */
        .countdown-timer {
            font-size: 14px;
            color: #d32f2f;
            margin-top: -10px;
            margin-bottom: 16px;
            font-weight: 600;
            text-align: left;
            display: block;
        }

        /* Terms & Conditions */
        .terms-section {
            margin-top: 20px;
            font-size: 12px;
            color: #555;
            border: 1px solid #eee;
            border-radius: 8px;
            padding: 15px;
            background: #fafafa;
            max-height: 200px;
            overflow-y: auto;
            line-height: 1.5;
        }

        .terms-section h4 {
            margin: 0 0 10px 0;
            color: #1976d2;
            font-size: 14px;
            text-align: left;
        }

        .terms-section ol {
            padding-left: 18px;
            margin: 8px 0;
        }

        .terms-section li {
            margin-bottom: 6px;
        }

        .terms-section strong {
            color: #333;
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
            .btn-container {
                flex-direction: column;
            }
            .btn {
                width: 100%;
            }
            .terms-section {
                font-size: 11px;
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
            <input 
                type="email" 
                name="email" 
                value="<?= e($_POST['email'] ?? '') ?>" 
                required 
                autocomplete="email" 
                placeholder="Enter your email" 
                <?= $is_locked_out ? 'disabled' : '' ?>
            />

            <label>Password:</label>
            <div class="password-container">
                <input 
                    type="password" 
                    name="password" 
                    id="password" 
                    required 
                    autocomplete="current-password" 
                    placeholder="Enter your password" 
                    <?= $is_locked_out ? 'disabled' : '' ?>
                />
                <i class="fas fa-eye-slash password-toggle" id="togglePassword" <?= $is_locked_out ? 'style="cursor: not-allowed;"' : '' ?>></i>
            </div>

            <!-- Countdown Timer -->
            <?php if ($is_locked_out): ?>
            <span class="countdown-timer" id="countdown">Try again in <span id="countdown-time">00:00</span></span>
            <?php endif; ?>

            <div class="btn-container">
                <button type="submit" class="btn" id="loginBtn" <?= $is_locked_out ? 'disabled' : '' ?>>
                    <i class="fas fa-sign-in-alt"></i> Login
                </button>
                <a href="register" class="btn secondary" <?= $is_locked_out ? 'style="pointer-events: none; opacity: 0.6;"' : '' ?>>
                    <i class="fas fa-user-plus"></i> Register
                </a>
            </div>

            <div class="form-footer">
                <a href="forgot_password">Forgot password?</a>
            </div>

            <!-- Terms and Conditions -->
            <div class="terms-section">
                <h4>Terms and Conditions</h4>
                <ol>
                    <li><strong>Definitions</strong>: System refers to the MCC Memo Generator. User refers to any authorized individual. Administrator manages the system.</li>
                    <li><strong>Acceptance</strong>: By logging in, you agree to these terms.</li>
                    <li><strong>Purpose</strong>: This system manages official memorandums securely.</li>
                    <li><strong>User Responsibilities</strong>: Use only your account. Do not share credentials.</li>
                    <li><strong>Data Privacy</strong>: Your data is stored securely and used only for memo generation and communication.</li>
                    <li><strong>Access</strong>: Access is restricted to authorized personnel only.</li>
                    <li><strong>Liability</strong>: The institution is not liable for unauthorized access due to shared passwords.</li>
                    <li><strong>Prohibited Actions</strong>: Sharing accounts, fake memos, or system abuse.</li>
                    <li><strong>Penalties</strong>: Misuse may result in suspension or disciplinary action.</li>
                    <li><strong>Maintenance</strong>: Scheduled downtime may occur. Users will be notified.</li>
                    <li><strong>Amendments</strong>: These terms may change without notice.</li>
                    <li><strong>Contact</strong>: For concerns, contact the system administrator.</li>
                </ol>
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

        // Toggle Password Visibility
        const passwordInput = document.getElementById('password');
        const togglePassword = document.getElementById('togglePassword');

        togglePassword?.addEventListener('click', function () {
            if (document.getElementById('loginBtn')?.disabled) return;
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });

        // Countdown Timer
        <?php if ($is_locked_out): ?>
        const unlockTimestamp = <?= $unlock_timestamp ?> * 1000;

        function updateCountdown() {
            const now = new Date().getTime();
            const distance = unlockTimestamp - now;

            if (distance <= 0) {
                clearInterval(timer);
                Swal.fire({
                    icon: 'info',
                    title: 'Lock Released',
                    text: 'You can now try logging in again.',
                    confirmButtonColor: '#1976d2'
                }).then(() => {
                    location.reload();
                });
            } else {
                const minutes = Math.floor(distance / 60000);
                const seconds = Math.floor((distance % 60000) / 1000);
                document.getElementById('countdown-time').textContent = 
                    `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
            }
        }

        updateCountdown();
        const timer = setInterval(updateCountdown, 1000);
        <?php endif; ?>

        // SweetAlert Notifications
        <?php if ($error && !$is_locked_out): ?>
        Swal.fire({
            icon: 'error',
            title: 'Login Failed',
            text: <?= json_encode(strip_tags($error)) ?>,
            confirmButtonColor: '#1976d2'
        });
        <?php endif; ?>

        <?php if ($email_error): ?>
        Swal.fire({
            icon: 'warning',
            title: 'Domain Required',
            text: <?= json_encode(strip_tags($email_error)) ?>,
            confirmButtonColor: '#1976d2'
        });
        <?php endif; ?>
    </script>
</body>
</html>