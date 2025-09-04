<?php
session_start();
require_once "includes/db.php";

// Initialize variables
$fullname = $username = $email = $contact = $birthday = $gender = $address = $department = $password = $confirm = '';
$role = 'user'; // Default role

$error = "";
$email_error = "";
$success = false;

// Role-to-department mapping
$role_to_department = [
    'student' => 'BSIT Department',
    'instructor' => 'Faculty',
    'library' => 'Library',
    'soa' => 'Office of SOA',
    'guidance' => 'Guidance Office',
    'school_counselor' => 'School Counselor',
    'dept_head_bsit' => 'BSIT Department',
    'dept_head_bsba' => 'BSBA Department',
    'dept_head_bshm' => 'HM Department',
    'dept_head_beed' => 'BEED Department',
    'non_teaching' => 'Non-Teaching Staff',
    'user' => 'Other'
];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $fullname = trim($_POST["fullname"] ?? '');
    $username = trim($_POST["username"] ?? '');
    $email = trim($_POST["email"] ?? '');
    $contact = trim($_POST["contact"] ?? '');
    $birthday = trim($_POST["birthday"] ?? '');
    $gender = trim($_POST["gender"] ?? '');
    $address = trim($_POST["address"] ?? '');
    $role = $_POST["role"] ?? 'user';
    $password = $_POST["password"] ?? '';
    $confirm = $_POST["confirm"] ?? '';

    // Auto-fill department based on role
    $department = $role_to_department[$role] ?? 'Other';

    // Validation
    if (empty($fullname)) {
        $error = "Full name is required.";
    } elseif (!preg_match("/^[a-zA-Z\s]+$/", $fullname)) {
        $error = "Full name can only contain letters and spaces.";
    }

    if (empty($username)) {
        $error = "Username is required.";
    } elseif (!preg_match("/^[a-zA-Z0-9]{3,}$/", $username)) {
        $error = "Username must be at least 3 characters and alphanumeric.";
    }

    if (empty($email)) {
        $error = "Email is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } else {
        $domain = strtolower(substr(strrchr($email, "@"), 1));
        if (!in_array($domain, ['mcclawis.edu.ph', 'gmail.com'])) {
            $email_error = "Please use @mcclawis.edu.ph or @gmail.com email.";
        }
    }

    if (!empty($contact)) {
        $contact = preg_replace('/\D/', '', $contact);
        if (strlen($contact) !== 11 || !preg_match('/^09\d{9}$/', $contact)) {
            $error = "Contact must be exactly 11 digits and start with 09.";
        }
    }

    if (empty($birthday)) {
        $error = "Birthday is required.";
    } else {
        $birth_date = new DateTime($birthday);
        $today = new DateTime();
        if ($birth_date > $today) {
            $error = "Birthday cannot be in the future.";
        } else {
            $age = $birth_date->diff($today)->y;
            if ($age < 18) {
                $error = "You must be at least 18 years old.";
            }
        }
    }

    if (empty($gender)) {
        $error = "Gender is required.";
    }

    if (empty($address)) {
        $error = "Address is required.";
    } elseif (!preg_match("/^[a-zA-Z\s,.'#-]+,\s*[a-zA-Z\s,.'#-]+,\s*[a-zA-Z\s,.'#-]+$/", $address)) {
        $error = "Address must be in the format: Barangay, Municipality, Province.";
    }

    if (empty($role)) {
        $error = "Role is required.";
    }

    if (empty($password)) {
        $error = "Password is required.";
    } elseif (strlen($password) < 11) {
        $error = "Password must be at least 11 characters long.";
    } elseif (!preg_match('/^(?=.*[a-zA-Z])(?=.*\d)[a-zA-Z\d@$!%*?&]{11,}$/', $password)) {
        $error = "Password must contain both letters and numbers.";
    }

    if ($password !== $confirm) {
        $error = "Passwords do not match.";
    }

    // Check duplicates
    if (empty($error) && empty($email_error)) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
        $stmt->bind_param("ss", $email, $username);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            if ($row['email'] === $email) {
                $error = "Email already registered.";
            } else {
                $error = "Username already taken.";
            }
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt_insert = $conn->prepare("INSERT INTO users (fullname, username, email, contact, birthday, gender, address, department, role, password) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt_insert->bind_param("ssssssssss", $fullname, $username, $email, $contact, $birthday, $gender, $address, $department, $role, $hashed_password);

            if ($stmt_insert->execute()) {
                $user_id = $stmt_insert->insert_id;
                $_SESSION['user_id'] = $user_id;
                $_SESSION['role'] = $role;
                $_SESSION['fullname'] = $fullname;
                $success = true;
            } else {
                $error = "Registration failed. Please try again.";
            }
            $stmt_insert->close();
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no"/>
    <title>Register - MCC Memo Generator</title>

    <!-- Google Font: Poppins -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />

    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        /* Reset & Base */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: #f5f7fa;
            color: #333;
            overflow-x: hidden;
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

        /* Responsive Container */
        .container {
            max-width: 540px;
            width: 95%;
            margin: 60px auto;
            padding: 35px 30px;
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1), 0 5px 10px rgba(0, 0, 0, 0.05);
            text-align: center;
        }

        /* Logo Text */
        .logo-text {
            font-size: clamp(1.8rem, 5vw, 2.8rem);
            font-weight: 700;
            color: #1976d2;
            letter-spacing: 1px;
            margin-bottom: 10px;
            position: relative;
            display: inline-block;
        }

        .logo-text::after {
            content: '';
            position: absolute;
            bottom: -6px;
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
            font-size: clamp(0.9rem, 3.5vw, 1rem);
            color: #666;
            margin-bottom: 28px;
            font-weight: 500;
        }

        /* Form */
        form {
            display: flex;
            flex-direction: column;
        }

        .form-group {
            margin-bottom: 16px;
        }

        label {
            text-align: left;
            font-weight: 600;
            color: #444;
            margin-bottom: 6px;
            font-size: clamp(0.85rem, 3vw, 0.95rem);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        label i {
            color: #1976d2;
            font-size: 0.9em;
        }

        input[type="text"],
        input[type="email"],
        input[type="date"],
        input[type="password"],
        select {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: clamp(0.9rem, 3.8vw, 1rem);
            transition: all 0.3s ease;
            font-family: 'Poppins', sans-serif;
        }

        input:focus, select:focus {
            outline: none;
            border-color: #1976d2;
            box-shadow: 0 0 0 3px rgba(25, 118, 210, 0.15);
        }

        /* Validation */
        input.valid {
            border-color: #28a745 !important;
            box-shadow: 0 0 0 2px rgba(40, 167, 69, 0.2) !important;
        }

        input.invalid {
            border-color: #dc3545 !important;
            box-shadow: 0 0 0 2px rgba(220, 53, 69, 0.2) !important;
        }

        /* Password Toggle */
        .password-container {
            position: relative;
        }

        .toggle-password {
            position: absolute;
            right: 12px;
            top: 34px;
            cursor: pointer;
            font-size: 1.1rem;
            color: #666;
        }

        /* Buttons */
        .btn-container {
            margin-top: 24px;
            display: flex;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn {
            padding: 14px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: clamp(0.9rem, 4vw, 1rem);
            font-weight: 600;
            transition: all 0.3s ease;
            font-family: 'Poppins', sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-width: 140px;
        }

        .btn.primary {
            background: #1976d2;
            color: white;
        }

        .btn.primary:hover {
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

        /* Responsive Adjustments */
        @media (max-width: 480px) {
            .container {
                margin: 40px 16px;
                padding: 30px 20px;
            }

            .btn-container {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                font-size: 1rem;
            }

            .form-group {
                margin-bottom: 14px;
            }

            input, select {
                font-size: 1rem;
                padding: 12px;
            }

            label {
                font-size: 0.95rem;
            }
        }

        /* Extra small devices (e.g., iPhone SE) */
        @media (max-width: 360px) {
            .container {
                padding: 25px 16px;
            }

            .logo-text {
                font-size: 1.8rem;
            }

            input, .btn {
                font-size: 0.95rem;
            }
        }

        /* Landscape mode for mobile */
        @media (max-height: 500px) and (orientation: landscape) {
            .container {
                margin: 20px auto;
                padding: 20px;
            }
        }
    </style>
</head>
<body>

    <!-- Background Particles -->
    <canvas id="particles"></canvas>

    <!-- Registration Form -->
    <div class="container">
        <h1 class="logo-text">MCC MEMO GEN</h1>
        <p class="tagline">Create Your Account</p>

        <form method="post" id="registerForm">
            <div class="form-group">
                <label><i class="fas fa-user"></i> Full Name</label>
                <input type="text" name="fullname" required value="<?= htmlspecialchars($fullname) ?>" id="fullname" placeholder="Juan Dela Cruz" />
            </div>

            <div class="form-group">
                <label><i class="fas fa-id-card"></i> Username</label>
                <input type="text" name="username" required value="<?= htmlspecialchars($username) ?>" id="username" placeholder="jdelacruz" />
            </div>

            <div class="form-group">
                <label><i class="fas fa-envelope"></i> Email</label>
                <input type="email" name="email" required value="<?= htmlspecialchars($email) ?>" id="email" placeholder="you@mcclawis.edu.ph or @gmail.com" />
            </div>

            <div class="form-group">
                <label><i class="fas fa-phone"></i> Contact</label>
                <input type="text" name="contact" value="<?= htmlspecialchars($contact) ?>" id="contact" placeholder="09123456789" maxlength="11" />
            </div>

            <div class="form-group">
                <label><i class="fas fa-calendar"></i> Birthday</label>
                <input type="date" name="birthday" required value="<?= htmlspecialchars($birthday) ?>" id="birthday" max="<?= date('Y-m-d') ?>" />
            </div>

            <div class="form-group">
                <label><i class="fas fa-venus-mars"></i> Gender</label>
                <select name="gender" required id="gender">
                    <option value="">Select...</option>
                    <option value="Male" <?= $gender === 'Male' ? 'selected' : '' ?>>Male</option>
                    <option value="Female" <?= $gender === 'Female' ? 'selected' : '' ?>>Female</option>
                    <option value="Other" <?= $gender === 'Other' ? 'selected' : '' ?>>Other</option>
                </select>
            </div>

            <div class="form-group">
                <label><i class="fas fa-map-marker-alt"></i> Address</label>
                <input type="text" name="address" required value="<?= htmlspecialchars($address) ?>" id="address" placeholder="Bunakan, Madridejos, Cebu" />
            </div>

            <div class="form-group">
                <label><i class="fas fa-id-badge"></i> Role</label>
                <select name="role" required id="role">
                    <option value="">Select Role</option>
                    <option value="student" <?= $role === 'student' ? 'selected' : '' ?>>Student</option>
                    <option value="instructor" <?= $role === 'instructor' ? 'selected' : '' ?>>Instructor</option>
                    <option value="library" <?= $role === 'library' ? 'selected' : '' ?>>Library</option>
                    <option value="guidance" <?= $role === 'guidance' ? 'selected' : '' ?>>Guidance Office</option>
                    <option value="school_counselor" <?= $role === 'school_counselor' ? 'selected' : '' ?>>School Counselor</option>
                    <option value="dept_head_bsit" <?= $role === 'dept_head_bsit' ? 'selected' : '' ?>>Dept Head - BSIT</option>
                    <option value="dept_head_bsba" <?= $role === 'dept_head_bsba' ? 'selected' : '' ?>>Dept Head - BSBA</option>
                    <option value="dept_head_bshm" <?= $role === 'dept_head_bshm' ? 'selected' : '' ?>>Dept Head - BSHM</option>
                    <option value="dept_head_beed" <?= $role === 'dept_head_beed' ? 'selected' : '' ?>>Dept Head - BEED</option>
                    <option value="non_teaching" <?= $role === 'non_teaching' ? 'selected' : '' ?>>Non-Teaching Staff</option>
                </select>
            </div>

            <div class="form-group">
                <label><i class="fas fa-building"></i> Department</label>
                <input type="text" name="department" readonly value="<?= htmlspecialchars($department) ?>" id="department" />
            </div>

            <div class="form-group">
                <label><i class="fas fa-lock"></i> Password</label>
                <div class="password-container">
                    <input type="password" name="password" required minlength="11" id="password" placeholder="At least 11 characters" />
                    <span class="toggle-password" onclick="togglePassword('password')">
                        <i class="fas fa-eye"></i>
                    </span>
                </div>
            </div>

            <div class="form-group">
                <label><i class="fas fa-check-circle"></i> Confirm Password</label>
                <div class="password-container">
                    <input type="password" name="confirm" required id="confirm" placeholder="Re-enter your password" />
                    <span class="toggle-password" onclick="togglePassword('confirm')">
                        <i class="fas fa-eye"></i>
                    </span>
                </div>
            </div>

            <div class="btn-container">
                <button type="submit" class="btn primary">
                    <i class="fas fa-user-plus"></i> Register
                </button>
                <a href="login.php" class="btn secondary">
                    <i class="fas fa-sign-in-alt"></i> Login
                </a>
            </div>
        </form>
    </div>

    <!-- Interactive Particles -->
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
        const colors = ['#1976d2', '#4fc3f7', '#bbdefb'];

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

        // Toggle Password
        function togglePassword(id) {
            const input = document.getElementById(id);
            const icon = input.nextElementSibling.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        }

        // Validation
        const validators = {
            fullname: (v) => /^[a-zA-Z\s]+$/.test(v),
            username: (v) => /^[a-zA-Z0-9]{3,}$/.test(v),
            email: (v) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v),
            contact: (v) => v === '' || /^09\d{9}$/.test(v.replace(/\D/g, '')),
            birthday: (v) => {
                if (!v) return false;
                const birth = new Date(v);
                const today = new Date();
                const age = today.getFullYear() - birth.getFullYear();
                return birth <= today && age >= 18;
            },
            gender: (v) => ['Male', 'Female', 'Other'].includes(v),
            address: (v) => /^[a-zA-Z\s,.'#-]+,\s*[a-zA-Z\s,.'#-]+,\s*[a-zA-Z\s,.'#-]+$/i.test(v),
            role: (v) => !!v,
            password: (v) => v.length >= 11 && /^(?=.*[a-zA-Z])(?=.*\d)[a-zA-Z\d@$!%*?&]{11,}$/.test(v),
            confirm: (v) => v === document.getElementById('password').value && v !== ''
        };

        function validateField(input, validFn) {
            const val = input.value.trim();
            input.classList.remove('valid', 'invalid');
            if (val && validFn(val)) {
                input.classList.add('valid');
            } else if (val) {
                input.classList.add('invalid');
            }
        }

        // Attach events
        ['fullname', 'username', 'email', 'birthday', 'gender', 'address'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.addEventListener('blur', e => validateField(e.target, validators[id]));
        });

        document.getElementById('contact').addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '');
            validateField(this, validators.contact);
        });

        document.getElementById('password').addEventListener('input', function() {
            validateField(this, validators.password);
            validateField(document.getElementById('confirm'), validators.confirm);
        });

        document.getElementById('confirm').addEventListener('input', e => validateField(e.target, validators.confirm));

        document.getElementById('role').addEventListener('change', function() {
            const deptMap = <?= json_encode($role_to_department) ?>;
            document.getElementById('department').value = deptMap[this.value] || 'Other';
            validateField(this, validators.role);
        });

        // Form submit
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            let valid = true;
            Object.keys(validators).forEach(key => {
                const input = document.getElementById(key);
                if (input && input.value.trim() !== '') {
                    if (!validators[key](input.value)) {
                        input.classList.remove('valid');
                        input.classList.add('invalid');
                        valid = false;
                    } else {
                        input.classList.remove('invalid');
                        input.classList.add('valid');
                    }
                }
            });

            if (!valid) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Validation Error',
                    text: 'Please fix the highlighted fields.',
                    confirmButtonColor: '#1976d2'
                });
            }
        });
    </script>

    <!-- Alerts -->
    <?php if ($email_error): ?>
    <script>
        Swal.fire({
            icon: 'warning',
            title: 'Email Required',
            text: <?= json_encode($email_error) ?>,
            confirmButtonColor: '#1976d2'
        });
    </script>
    <?php endif; ?>

    <?php if ($error): ?>
    <script>
        Swal.fire({
            icon: 'error',
            title: 'Registration Failed',
            text: <?= json_encode($error) ?>,
            confirmButtonColor: '#1976d2'
        });
    </script>
    <?php endif; ?>

    <?php if ($success): ?>
    <script>
        Swal.fire({
            icon: 'success',
            title: 'Registration Successful!',
            text: 'Welcome to MCC Memo Generator! Redirecting to dashboard...',
            confirmButtonColor: '#1976d2',
            timer: 2000,
            timerProgressBar: true
        }).then(() => {
            window.location.href = "user/dashboard.php";
        });
    </script>
    <?php endif; ?>
</body>
</html>