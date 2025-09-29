<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once 'auth_admin.php';

// 🔐 Admin-only access
if ($_SESSION['role'] !== 'admin') {
    die("Access denied. Admins only.");
}

$error = "";
$success = "";

// Initialize form data
$fullname = $email = $contact = $username = $password = $birthday = $gender = $address = $role = '';

// ✅ UPDATED ROLE-TO-DEPARTMENT MAPPING (from your request)
$role_to_department = [
    'admin' => 'Office of the College President',
    'student' => 'BSIT Department',
    'school_counselor' => 'School Counselor',
    'library' => 'Library',
    'soa' => 'Office of SOA',
    'guidance' => 'Guidance Office',
    'dept_head_bsit' => 'BSIT Department',
    'dept_head_bsba' => 'BSBA Department',
    'dept_head_bshm' => 'HM Department',
    'dept_head_beed' => 'BEED Department',
    'instructor' => 'Faculty',
    'non_teaching' => 'Non-Teaching Staff',
    'student_bsit' => 'BSIT Department',
    'student_bshm' => 'HM Department',
    'student_bsba' => 'BSBA Department',
    'student_beed' => 'BEED Department'
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
                $_SESSION['msg_type'] = 'success';
                $_SESSION['msg_text'] = 'User added successfully!';
                header("Location: users_add.php");
                exit;
            } else {
                $error = "Database error.";
            }
            $stmt_insert->close();
        }
        $stmt->close();
    }
}

// Get session message
$msg_type = $_SESSION['msg_type'] ?? null;
$msg_text = $_SESSION['msg_text'] ?? null;
unset($_SESSION['msg_type'], $_SESSION['msg_text']);

include "../includes/admin_sidebar.php";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Add User - Admin Panel</title>

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500&display=swap" rel="stylesheet">

    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />

    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" />

    <style>
        body {
            font-family: 'Roboto', Arial, sans-serif;
            background-color: #f4f6f9;
            margin: 0;
            padding: 0;
            color: #333;
        }

        .container {
            max-width: 600px;
            margin: 20px auto;
            padding: 25px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        h2 {
            text-align: center;
            color: #2c3e50;
            margin-bottom: 20px;
            font-weight: 500;
        }

        .form-group {
            margin-bottom: 18px;
        }

        label {
            display: block;
            font-weight: 500;
            margin-bottom: 6px;
            color: #2c3e50;
        }

        input[type="text"],
        input[type="email"],
        input[type="date"],
        select,
        textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
            box-sizing: border-box;
            outline: none;
        }

        input:focus,
        select:focus,
        textarea:focus {
            border-color: #3498db;
            box-shadow: 0 0 5px rgba(52, 152, 219, 0.2);
        }

        input.valid {
            border-color: #28a745 !important;
            box-shadow: 0 0 0 2px rgba(40, 167, 69, 0.2) !important;
        }

        input.invalid {
            border-color: #dc3545 !important;
            box-shadow: 0 0 0 2px rgba(220, 53, 69, 0.2) !important;
        }

        /* Password Field with Centered Eye Icon */
        .password-container {
            display: flex;
            align-items: center;
            width: 100%;
            border: 1px solid #ddd;
            border-radius: 8px;
            overflow: hidden;
            background: white;
        }

        .password-input {
            flex-grow: 1;
            padding: 12px;
            border: none;
            outline: none;
            font-size: 14px;
        }

        .password-input:focus {
            box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.25);
        }

        .toggle-password {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 44px;
            height: 44px;
            background: #f1f3f5;
            border: none;
            cursor: pointer;
            color: #666;
            font-size: 1rem;
            transition: all 0.2s ease;
        }

        .toggle-password:hover {
            background: #e9ecef;
            color: #495057;
        }

        /* Buttons */
        .btn-container {
            display: flex;
            gap: 12px;
            justify-content: center;
            margin-top: 25px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: #007bff;
            color: white;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn:hover {
            opacity: 0.9;
        }

        /* Responsive */
        @media (max-width: 600px) {
            .container {
                margin: 10px;
                padding: 15px;
            }

            .btn span {
                display: none;
            }

            .btn i {
                font-size: 1.2rem;
            }

            input[type="text"],
            input[type="email"],
            input[type="password"],
            select,
            textarea {
                font-size: 13px;
                padding: 10px;
            }
        }
    </style>
</head>
<body>
<div class="container">
    <h2><i class="fas fa-user-plus"></i> Add New User</h2>

    <!-- SweetAlert2 Message -->
    <?php if ($msg_type && $msg_text): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: '<?= $msg_type ?>',
                    title: '<?= $msg_type === 'success' ? 'Success!' : 'Error!' ?>',
                    text: '<?= addslashes($msg_text) ?>'
                });
            });
        </script>
    <?php endif; ?>

    <form method="post" id="userForm">
        <div class="form-group">
            <label for="fullname"><i class="fas fa-user"></i> Full Name</label>
            <input type="text" name="fullname" id="fullname" required value="<?= htmlspecialchars($fullname) ?>" />
        </div>

        <div class="form-group">
            <label for="email"><i class="fas fa-envelope"></i> Email</label>
            <input type="email" name="email" id="email" required value="<?= htmlspecialchars($email) ?>" />
        </div>

        <div class="form-group">
            <label for="contact"><i class="fas fa-phone"></i> Contact</label>
            <input type="text" name="contact" id="contact" required value="<?= htmlspecialchars($contact) ?>" placeholder="09123456789" maxlength="11" />
        </div>

        <div class="form-group">
            <label for="username"><i class="fas fa-id-card"></i> Username</label>
            <input type="text" name="username" id="username" required value="<?= htmlspecialchars($username) ?>" />
        </div>

        <div class="form-group">
            <label for="password"><i class="fas fa-lock"></i> Password</label>
            <div class="password-container">
                <input 
                    type="password" 
                    name="password" 
                    id="password" 
                    class="password-input" 
                    required 
                    minlength="11" 
                />
                <span class="toggle-password" onclick="togglePassword('password')" title="Toggle visibility">
                    <i class="fas fa-eye"></i>
                </span>
            </div>
        </div>

        <div class="form-group">
            <label for="birthday"><i class="fas fa-calendar"></i> Birthday</label>
            <input type="date" name="birthday" id="birthday" required value="<?= htmlspecialchars($birthday) ?>" max="<?= date('Y-m-d') ?>" />
        </div>

        <div class="form-group">
            <label for="gender"><i class="fas fa-venus-mars"></i> Gender</label>
            <select name="gender" id="gender" required>
                <option value="">Select...</option>
                <option value="Male" <?= $gender === 'Male' ? 'selected' : '' ?>>Male</option>
                <option value="Female" <?= $gender === 'Female' ? 'selected' : '' ?>>Female</option>
                <option value="Other" <?= $gender === 'Other' ? 'selected' : '' ?>>Other</option>
            </select>
        </div>

        <div class="form-group">
            <label for="address"><i class="fas fa-map-marker-alt"></i> Address</label>
            <textarea name="address" id="address" required rows="3"><?= htmlspecialchars($address) ?></textarea>
        </div>

        <div class="form-group">
            <label for="role"><i class="fas fa-id-badge"></i> Role</label>
            <select name="role" id="role" required onchange="updateDepartment()">
                <option value="">Select Role</option>
                <option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>Admin</option>
                <option value="student" <?= $role === 'student' ? 'selected' : '' ?>>Student</option>
                <option value="school_counselor" <?= $role === 'school_counselor' ? 'selected' : '' ?>>School Counselor</option>
                <option value="library" <?= $role === 'library' ? 'selected' : '' ?>>Library</option>
                <option value="soa" <?= $role === 'soa' ? 'selected' : '' ?>>Office of SOA</option>
                <option value="guidance" <?= $role === 'guidance' ? 'selected' : '' ?>>Guidance Office</option>
                <option value="dept_head_bsit" <?= $role === 'dept_head_bsit' ? 'selected' : '' ?>>Dept Head - BSIT</option>
                <option value="dept_head_bsba" <?= $role === 'dept_head_bsba' ? 'selected' : '' ?>>Dept Head - BSBA</option>
                <option value="dept_head_bshm" <?= $role === 'dept_head_bshm' ? 'selected' : '' ?>>Dept Head - BSHM</option>
                <option value="dept_head_beed" <?= $role === 'dept_head_beed' ? 'selected' : '' ?>>Dept Head - BEED</option>
                <option value="instructor" <?= $role === 'instructor' ? 'selected' : '' ?>>Instructor</option>
                <option value="non_teaching" <?= $role === 'non_teaching' ? 'selected' : '' ?>>Non-Teaching Staff</option>
                <option value="student_bsit" <?= $role === 'student_bsit' ? 'selected' : '' ?>>Student - BSIT</option>
                <option value="student_bshm" <?= $role === 'student_bshm' ? 'selected' : '' ?>>Student - BSHM</option>
                <option value="student_bsba" <?= $role === 'student_bsba' ? 'selected' : '' ?>>Student - BSBA</option>
                <option value="student_beed" <?= $role === 'student_beed' ? 'selected' : '' ?>>Student - BEED</option>
            </select>
        </div>

        <!-- Hidden field for department (auto-filled) -->
        <input type="hidden" name="department" id="department" value="<?= htmlspecialchars($role_to_department[$role] ?? '') ?>" />

        <div class="btn-container">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> <span>Add User</span>
            </button>
            <a href="users.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> <span>Back to Users</span>
            </a>
        </div>
    </form>
</div>

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
// Toggle password visibility
function togglePassword(id) {
    const input = document.getElementById(id);
    const icon = event.currentTarget.querySelector('i'); // Only affects clicked icon

    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
    }
}

// Update department based on role
function updateDepartment() {
    const role = document.getElementById('role').value;
    const deptMap = {
        'admin': 'Office of the College President',
        'student': 'BSIT Department',
        'school_counselor': 'School Counselor',
        'library': 'Library',
        'soa': 'Office of SOA',
        'guidance': 'Guidance Office',
        'dept_head_bsit': 'BSIT Department',
        'dept_head_bsba': 'BSBA Department',
        'dept_head_bshm': 'HM Department',
        'dept_head_beed': 'BEED Department',
        'instructor': 'Faculty',
        'non_teaching': 'Non-Teaching Staff',
        'student_bsit': 'BSIT Department',
        'student_bshm': 'HM Department',
        'student_bsba': 'BSBA Department',
        'student_beed': 'BEED Department'
    };
    document.getElementById('department').value = deptMap[role] || '';
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

// Attach blur/change events
document.getElementById('fullname').addEventListener('blur', e => validateField(e.target, validators.fullname));
document.getElementById('email').addEventListener('blur', e => validateField(e.target, validators.email));
document.getElementById('contact').addEventListener('blur', e => validateField(e.target, validators.contact));
document.getElementById('username').addEventListener('blur', e => validateField(e.target, validators.username));
document.getElementById('password').addEventListener('blur', e => validateField(e.target, validators.password));
document.getElementById('birthday').addEventListener('change', e => validateField(e.target, validators.birthday));
document.getElementById('gender').addEventListener('change', e => validateField(e.target, validators.gender));
document.getElementById('address').addEventListener('blur', e => validateField(e.target, validators.address));
document.getElementById('role').addEventListener('change', e => {
    validateField(e.target, validators.role);
    updateDepartment();
});

// Form submit validation
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
        Swal.fire({
            icon: 'warning',
            title: 'Validation Error',
            text: 'Please fix the highlighted fields.'
        });
    }
});
</script>

<?php include "../includes/admin_footer.php"; ?>
</body>
</html>