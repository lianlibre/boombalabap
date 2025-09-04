<?php
session_start();
require_once "../includes/db.php";
require_once "../includes/permissions.php";
require_once 'auth_admin.php';
include "../includes/admin_sidebar.php";
if ($_SESSION['role'] !== 'admin') {
    die("Access denied. Admins only.");
}

$error = "";
$success = "";

// Initialize form data
$fullname = $email = $contact = $username = $password = $birthday = $gender = $address = $role = '';

// Role-to-department mapping
$role_to_department = [
    'admin' => 'Office of the College President',
    'student' => 'BSIT Department', // Default for students
    'instructor' => 'Faculty',
    'library' => 'Library',
    'soa' => 'Office of SOA',
    'guidance' => 'Guidance Office',
    'school_counselor' => 'School Counselor',
    'dept_head_bsit' => 'BSIT Department',
    'dept_head_bsba' => 'BSBA Department',
    'dept_head_bshm' => 'HM Department',
    'dept_head_beed' => 'BEED Department',
    'non_teaching' => 'Non-Teaching Staff'
];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Sanitize inputs
    $fullname = trim($_POST["fullname"] ?? '');
    $email = trim($_POST["email"] ?? '');
    $contact = trim($_POST["contact"] ?? '');
    $username = trim($_POST["username"] ?? '');
    $password = $_POST["password"] ?? '';
    $birthday = $_POST["birthday"] ?? '';
    $gender = $_POST["gender"] ?? '';
    $address = trim($_POST["address"] ?? '');
    $role = $_POST["role"] ?? '';

    // Validate required fields
    if (empty($fullname)) {
        $error = "Full name is required.";
    } elseif (!preg_match("/^[a-zA-Z\s]+$/", $fullname)) {
        $error = "Full name can only contain letters and spaces.";
    }

    if (empty($email)) {
        $error = "Email is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } else {
        $domain = substr(strrchr($email, "@"), 1);
        if (!in_array(strtolower($domain), ['mcclawis.edu.ph', 'gmail.com'])) {
            $error = "Please use @mcclawis.edu.ph or @gmail.com email.";
        }
    }

    if (empty($contact)) {
        $error = "Contact number is required.";
    } elseif (!preg_match("/^09\d{9}$/", $contact)) {
        $error = "Contact must be 11 digits starting with 09.";
    }

    if (empty($username)) {
        $error = "Username is required.";
    } elseif (!preg_match("/^[a-zA-Z0-9]{3,}$/", $username)) {
        $error = "Username must be at least 3 characters and alphanumeric.";
    }

    if (empty($password)) {
        $error = "Password is required.";
    } elseif (strlen($password) < 11 || !preg_match('/^(?=.*[a-zA-Z])(?=.*\d)[a-zA-Z\d@$!%*?&]{11,}$/', $password)) {
        $error = "Password must be at least 11 characters and contain both letters and numbers.";
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

    if (empty($error)) {
        // Check if email already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $error = "User with this email already exists.";
        } else {
            // Auto-fill department based on role
            $department = $role_to_department[$role] ?? 'Unknown';

            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Insert user
            $stmt_insert = $conn->prepare("INSERT INTO users (fullname, email, contact, username, password, role, birthday, gender, address, department) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt_insert->bind_param("ssssssssss", $fullname, $email, $contact, $username, $hashed_password, $role, $birthday, $gender, $address, $department);

            if ($stmt_insert->execute()) {
                $success = "User added successfully!";
                header("Location: users.php?msg=added");
                exit;
            } else {
                $error = "Database error.";
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
    <title>Add User - Admin Panel</title>
    <link rel="stylesheet" href="../includes/user_style.css" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet" />
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f7f9fc;
            color: #333;
            margin: 0;
            padding: 0;
        }

        .container {
            max-width: 800px;
            margin: 40px auto;
            padding: 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 16px rgba(0,0,0,0.1);
        }

        h2 {
            color: #1976d2;
            margin-bottom: 20px;
            font-weight: 500;
            text-align: center;
        }

        .form-group {
            margin-bottom: 18px;
        }

        label {
            display: block;
            font-weight: 600;
            margin-bottom: 6px;
            color: #444;
        }

        input[type="text"],
        input[type="email"],
        input[type="date"],
        input[type="password"],
        select,
        textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 1rem;
            transition: border 0.3s ease;
            box-sizing: border-box;
        }

        input.valid {
            border-color: #28a745 !important;
            box-shadow: 0 0 0 2px rgba(40, 167, 69, 0.2) !important;
        }

        input.invalid {
            border-color: #dc3545 !important;
            box-shadow: 0 0 0 2px rgba(220, 53, 69, 0.2) !important;
        }

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

        .btn-container {
            margin-top: 24px;
            display: flex;
            gap: 12px;
            justify-content: center;
        }

        .btn {
            padding: 10px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            font-weight: 600;
            transition: background 0.3s ease;
        }

        .btn.primary {
            background: #1976d2;
            color: white;
        }

        .btn.secondary {
            background: #6c757d;
            color: white;
        }

        .btn:hover {
            opacity: 0.9;
        }

        .alert {
            padding: 14px;
            margin-bottom: 18px;
            border-radius: 6px;
            font-size: 14px;
        }

        .alert.error { 
            background: #f8d7da; 
            color: #721c24; 
            border: 1px solid #f5c6cb;
        }

        .alert.success { 
            background: #d4edda; 
            color: #155724; 
            border: 1px solid #c3e6cb;
        }

        @media (max-width: 600px) {
            .container {
                padding: 15px;
            }
        }
    </style>
</head>
<body>
<div class="container">
    <h2><i class="fas fa-user-plus"></i> Add New User</h2>

    <?php if ($error): ?>
        <div class="alert error">
            <i class="fas fa-exclamation-triangle"></i> <strong>Error:</strong> <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert success">
            <i class="fas fa-check-circle"></i> <strong>Success:</strong> <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>

    <form method="post" id="userForm">
        <div class="form-group">
            <label><i class="fas fa-user"></i> Full Name</label>
            <input type="text" name="fullname" required value="<?= htmlspecialchars($fullname) ?>" id="fullname" />
        </div>

        <div class="form-group">
            <label><i class="fas fa-envelope"></i> Email</label>
            <input type="email" name="email" required value="<?= htmlspecialchars($email) ?>" id="email" />
        </div>

        <div class="form-group">
            <label><i class="fas fa-phone"></i> Contact</label>
            <input type="text" name="contact" required value="<?= htmlspecialchars($contact) ?>" id="contact" placeholder="09123456789" maxlength="11" />
        </div>

        <div class="form-group">
            <label><i class="fas fa-id-card"></i> Username</label>
            <input type="text" name="username" required value="<?= htmlspecialchars($username) ?>" id="username" />
        </div>

        <div class="form-group">
            <label><i class="fas fa-lock"></i> Password</label>
            <div class="password-container">
                <input type="password" name="password" required minlength="11" id="password" />
                <span class="toggle-password" onclick="togglePassword('password')">
                    <i class="fas fa-eye"></i>
                </span>
            </div>
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
            <textarea name="address" required id="address" rows="3"><?= htmlspecialchars($address) ?></textarea>
        </div>

        <div class="form-group">
            <label><i class="fas fa-id-badge"></i> Role</label>
            <select name="role" required id="role">
                <option value="">Select Role</option>
                <option value="admin">Admin</option>
                <option value="student">Student</option>
                <option value="instructor">Instructor</option>
                <option value="library">Library</option>
                <option value="soa">Office of SOA</option>
                <option value="guidance">Guidance Office</option>
                <option value="school_counselor">School Counselor</option>
                <option value="dept_head_bsit">Dept Head - BSIT</option>
                <option value="dept_head_bsba">Dept Head - BSBA</option>
                <option value="dept_head_bshm">Dept Head - BSHM</option>
                <option value="dept_head_beed">Dept Head - BEED</option>
                <option value="non_teaching">Non-Teaching Staff</option>
            </select>
        </div>

        <!-- Department is auto-filled based on role -->
        <div class="form-group">
           <!-- <label><i class="fas fa-building"></i> Department</label> 
            <input type="text" name="department" readonly value="<?= htmlspecialchars($role_to_department[$role] ?? '') ?>" id="department" /> !-->
        </div>

        <div class="btn-container">
            <button type="submit" class="btn primary">
                <i class="fas fa-save"></i> Add User
            </button>
            <a href="users.php" class="btn secondary">
                <i class="fas fa-arrow-left"></i> Back to Users
            </a>
        </div>
    </form>
</div>

<script>
// Toggle password visibility
function togglePassword(id) {
    const input = document.getElementById(id);
    const icon = input.nextElementSibling.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

// Real-time validation
function validateField(input, validatorFn) {
    const value = input.value.trim();
    const isValid = value === '' ? true : validatorFn(value);

    input.classList.remove('valid', 'invalid');
    if (value !== '' && isValid) {
        input.classList.add('valid');
    } else if (value !== '') {
        input.classList.add('invalid');
    }
}

// Validators
const validators = {
    fullname: (v) => /^[a-zA-Z\s]+$/.test(v),
    email: (v) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v),
    contact: (v) => /^09\d{9}$/.test(v),
    username: (v) => /^[a-zA-Z0-9]{3,}$/.test(v),
    password: (v) => v.length >= 11 && /^(?=.*[a-zA-Z])(?=.*\d)[a-zA-Z\d@$!%*?&]{11,}$/.test(v),
    birthday: (v) => {
        if (!v) return false;
        const birth = new Date(v);
        const today = new Date();
        const age = today.getFullYear() - birth.getFullYear();
        return birth <= today && age >= 18;
    },
    gender: (v) => ['Male', 'Female', 'Other'].includes(v),
    address: (v) => /^[a-zA-Z\s,.'#-]+,\s*[a-zA-Z\s,.'#-]+,\s*[a-zA-Z\s,.'#-]+$/i.test(v),
    role: (v) => v.length > 0
};

// Attach validation
document.getElementById('fullname').addEventListener('blur', function() {
    validateField(this, validators.fullname);
});
document.getElementById('email').addEventListener('blur', function() {
    validateField(this, validators.email);
});
document.getElementById('contact').addEventListener('blur', function() {
    validateField(this, validators.contact);
});
document.getElementById('username').addEventListener('blur', function() {
    validateField(this, validators.username);
});
document.getElementById('password').addEventListener('blur', function() {
    validateField(this, validators.password);
});
document.getElementById('birthday').addEventListener('change', function() {
    validateField(this, validators.birthday);
});
document.getElementById('gender').addEventListener('change', function() {
    validateField(this, validators.gender);
});
document.getElementById('address').addEventListener('blur', function() {
    validateField(this, validators.address);
});
document.getElementById('role').addEventListener('change', function() {
    validateField(this, validators.role);
    const role = this.value;
    const dept = {
        'admin': 'Office of the College President',
        'student': 'BSIT Department',
        'instructor': 'Faculty',
        'library': 'Library',
        'soa': 'Office of SOA',
        'guidance': 'Guidance Office',
        'school_counselor': 'School Counselor',
        'dept_head_bsit': 'BSIT Department',
        'dept_head_bsba': 'BSBA Department',
        'dept_head_bshm': 'HM Department',
        'dept_head_beed': 'BEED Department',
        'non_teaching': 'Non-Teaching Staff'
    }[role] || '';
    document.getElementById('department').value = dept;
});

// Validate on submit
document.getElementById('userForm').addEventListener('submit', function(e) {
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
        alert("Please fix the highlighted fields.");
    }
});
</script>

<?php include "../includes/admin_footer.php"; ?>
</body>
</html>