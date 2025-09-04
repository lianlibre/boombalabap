<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/permissions.php";

// Only allow admin
if ($_SESSION['role'] !== 'admin') {
    die("Access denied. Admins only.");
}

$id = intval($_GET['id']);
$user = $conn->query("SELECT * FROM users WHERE id=$id")->fetch_assoc();
if (!$user) {
    echo "<div class='container'><h2>User not found.</h2></div>";
    include "../includes/admin_footer.php";
    exit;
}

$error = "";
$success = "";

// Role-to-department mapping
$role_to_department = [
    'admin' => 'Office of the College President',
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
    'non_teaching' => 'Non-Teaching Staff'
];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $fullname = trim($_POST["fullname"] ?? '');
    $email = trim($_POST["email"] ?? '');
    $contact = trim($_POST["contact"] ?? '');
    $role = $_POST["role"] ?? '';
    $birthday = $_POST["birthday"] ?? '';
    $gender = $_POST["gender"] ?? '';
    $address = trim($_POST["address"] ?? '');
    $new_password = $_POST["password"] ?? '';

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

    if (empty($role)) {
        $error = "Role is required.";
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

    if (!empty($new_password) && strlen($new_password) < 11) {
        $error = "Password must be at least 11 characters long.";
    }

    if (empty($error)) {
        // Auto-fill department based on role
        $department = $role_to_department[$role] ?? 'Unknown';

        // Update user
        $stmt = $conn->prepare("UPDATE users SET fullname=?, email=?, contact=?, role=?, birthday=?, gender=?, address=?, department=? WHERE id=?");
        $stmt->bind_param("ssssssssi", $fullname, $email, $contact, $role, $birthday, $gender, $address, $department, $id);
        $stmt->execute();
        $stmt->close();

        // Update password if provided
        if (!empty($new_password)) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password=? WHERE id=?");
            $stmt->bind_param("si", $hashed_password, $id);
            $stmt->execute();
            $stmt->close();
        }

        header("Location: users.php?msg=updated");
        exit;
    }
}

include "../includes/admin_sidebar.php";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Edit User</title>
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
    <h2><i class="fas fa-user-edit"></i> Edit User</h2>

    <?php if ($error): ?>
        <div class="alert error">
            <i class="fas fa-exclamation-triangle"></i> <strong>Error:</strong> <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="post" id="userForm">
        <div class="form-group">
            <label><i class="fas fa-user"></i> Full Name</label>
            <input type="text" name="fullname" required value="<?= htmlspecialchars($user['fullname']) ?>" id="fullname" />
        </div>

        <div class="form-group">
            <label><i class="fas fa-envelope"></i> Email</label>
            <input type="email" name="email" required value="<?= htmlspecialchars($user['email']) ?>" id="email" />
        </div>

        <div class="form-group">
            <label><i class="fas fa-phone"></i> Contact</label>
            <input type="text" name="contact" required value="<?= htmlspecialchars($user['contact']) ?>" id="contact" placeholder="09123456789" maxlength="11" />
        </div>

        <div class="form-group">
            <label><i class="fas fa-calendar"></i> Birthday</label>
            <input type="date" name="birthday" required value="<?= htmlspecialchars($user['birthday']) ?>" id="birthday" max="<?= date('Y-m-d') ?>" />
        </div>

        <div class="form-group">
            <label><i class="fas fa-venus-mars"></i> Gender</label>
            <select name="gender" required id="gender">
                <option value="">Select...</option>
                <option value="Male" <?= $user['gender'] === 'Male' ? 'selected' : '' ?>>Male</option>
                <option value="Female" <?= $user['gender'] === 'Female' ? 'selected' : '' ?>>Female</option>
                <option value="Other" <?= $user['gender'] === 'Other' ? 'selected' : '' ?>>Other</option>
            </select>
        </div>

        <div class="form-group">
            <label><i class="fas fa-map-marker-alt"></i> Address</label>
            <textarea name="address" required id="address" rows="3"><?= htmlspecialchars($user['address']) ?></textarea>
        </div>

        <div class="form-group">
            <label><i class="fas fa-id-badge"></i> Role</label>
            <select name="role" required id="role">
                <option value="">Select Role</option>
                <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                <option value="student" <?= $user['role'] === 'student' ? 'selected' : '' ?>>Student</option>
                <option value="instructor" <?= $user['role'] === 'instructor' ? 'selected' : '' ?>>Instructor</option>
                <option value="library" <?= $user['role'] === 'library' ? 'selected' : '' ?>>Library</option>
                <option value="soa" <?= $user['role'] === 'soa' ? 'selected' : '' ?>>Office of SOA</option>
                <option value="guidance" <?= $user['role'] === 'guidance' ? 'selected' : '' ?>>Guidance Office</option>
                <option value="school_counselor" <?= $user['role'] === 'school_counselor' ? 'selected' : '' ?>>School Counselor</option>
                <option value="dept_head_bsit" <?= $user['role'] === 'dept_head_bsit' ? 'selected' : '' ?>>Dept Head - BSIT</option>
                <option value="dept_head_bsba" <?= $user['role'] === 'dept_head_bsba' ? 'selected' : '' ?>>Dept Head - BSBA</option>
                <option value="dept_head_bshm" <?= $user['role'] === 'dept_head_bshm' ? 'selected' : '' ?>>Dept Head - BSHM</option>
                <option value="dept_head_beed" <?= $user['role'] === 'dept_head_beed' ? 'selected' : '' ?>>Dept Head - BEED</option>
                <option value="non_teaching" <?= $user['role'] === 'non_teaching' ? 'selected' : '' ?>>Non-Teaching Staff</option>
            </select>
        </div>

        <div class="form-group">
            <label><i class="fas fa-building"></i> Department</label>
            <input type="text" name="department" readonly value="<?= htmlspecialchars($user['department']) ?>" id="department" />
        </div>

        <div class="form-group">
            <label><i class="fas fa-lock"></i> Password (leave blank to keep current)</label>
            <div class="password-container">
                <input type="password" name="password" id="password" />
                <span class="toggle-password" onclick="togglePassword('password')">
                    <i class="fas fa-eye"></i>
                </span>
            </div>
        </div>

        <div class="btn-container">
            <button type="submit" class="btn primary">
                <i class="fas fa-save"></i> Update User
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