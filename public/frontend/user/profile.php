<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
#require_once '../admin/auth_admin.php';
require_once '../includes/permissions.php';

$user_id = $_SESSION['user_id'];
$user = $conn->query("SELECT * FROM users WHERE id=$user_id")->fetch_assoc();

$departments = [
    "Office of the College President",
    "Office of the Registrar",
    "Office of the Student Affairs",
    "Faculty",
    "Finance Office",
    "Library",
    "BSIT Department",
    "HM Department",
    "BEED Department",
    "BSBA Department",
    "Guidance Office",
    "Other"
];

$errors = [];
$success = false;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $fullname = trim($_POST["fullname"] ?? '');
    $username = trim($_POST["username"] ?? '');
    $email = trim($_POST["email"] ?? '');
    $contact = trim($_POST["contact"] ?? '');
    $birthday = trim($_POST["birthday"] ?? '');
    $gender = trim($_POST["gender"] ?? '');
    $address = trim($_POST["address"] ?? '');

    // Validate Full Name
    if (empty($fullname)) {
        $errors[] = "Full name is required.";
    } elseif (!preg_match("/^[a-zA-Z\s]+$/", $fullname)) {
        $errors[] = "Full name can only contain letters and spaces.";
    }

    // Validate Username
    if (empty($username)) {
        $errors[] = "Username is required.";
    } elseif (!preg_match("/^[a-zA-Z0-9_-]{3,}$/", $username)) {
        $errors[] = "Username must be at least 3 characters and alphanumeric.";
    }

    // Validate Email
    if (empty($email)) {
        $errors[] = "Email is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }

    // Validate Contact (Philippine Mobile: 11 digits, starts with 09)
    if (!empty($contact)) {
        $clean_contact = preg_replace('/\D/', '', $contact); // Remove non-digits
        if (strlen($clean_contact) !== 11) {
            $errors[] = "Contact must be exactly 11 digits (e.g., 09123456789).";
        } elseif (!preg_match('/^09\d{9}$/', $clean_contact)) {
            $errors[] = "Contact must start with '09' (e.g., 09123456789).";
        } else {
            $contact = $clean_contact; // Save clean version
        }
    }

    // Validate Birthday (age >= 18, not future)
    if (empty($birthday)) {
        $errors[] = "Birthday is required.";
    } else {
        $birth_date = new DateTime($birthday);
        $today = new DateTime();
        if ($birth_date > $today) {
            $errors[] = "Birthday cannot be in the future.";
        } else {
            $age = $birth_date->diff($today)->y;
            if ($age < 18) {
                $errors[] = "You must be at least 18 years old.";
            }
        }
    }

    // Validate Gender
    if (!in_array($gender, ['Male', 'Female', 'Other'])) {
        $errors[] = "Please select a valid gender.";
    }

    // Validate Address: Barangay, Municipality, Province
    if (empty($address)) {
        $errors[] = "Address is required.";
    } else {
        if (!preg_match("/^[a-zA-Z\s]+,\s*[a-zA-Z\s]+,\s*[a-zA-Z\s]+$/", $address)) {
            $errors[] = "Address must be in the format: Barangay, Municipality, Province (e.g., Bunakan, Madridejos, Cebu)";
        } else {
            $parts = array_map('trim', explode(',', $address));
            foreach ($parts as $part) {
                if (strlen($part) < 2) {
                    $errors[] = "Each part of the address must be at least 2 characters.";
                    break;
                }
            }
        }
    }

    // Update user if no errors
    if (empty($errors)) {
        $stmt = $conn->prepare("UPDATE users SET fullname=?, username=?, email=?, contact=?, birthday=?, gender=?, address=? WHERE id=?");
        $stmt->bind_param("sssssssi", $fullname, $username, $email, $contact, $birthday, $gender, $address, $user_id);
        $stmt->execute();
        $stmt->close();
        $success = true;
    }

    // Password Change
    $current_pass = $_POST['current_password'] ?? '';
    $new_pass = $_POST['new_password'] ?? '';
    $confirm_pass = $_POST['confirm_password'] ?? '';

    if (!empty($current_pass) || !empty($new_pass)) {
        if (empty($current_pass)) {
            $errors[] = "Current password is required.";
        }
        if (empty($new_pass)) {
            $errors[] = "New password is required.";
        }
        if ($new_pass !== $confirm_pass) {
            $errors[] = "New passwords do not match.";
        } elseif (!preg_match('/^(?=.*[a-zA-Z])(?=.*\d)[a-zA-Z\d@$!%*?&]{8,}$/', $new_pass)) {
            $errors[] = "Password must be 8+ chars and contain letters & numbers.";
        } elseif (!password_verify($current_pass, $user['password'])) {
            $errors[] = "Current password is incorrect.";
        } else {
            $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->bind_param("si", $hashed, $user_id);
            $stmt->execute();
            $stmt->close();
            $success = true;
        }
    }

    // Update last checked
    $stmt = $conn->prepare("UPDATE users SET last_checked = NOW() WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();

    if ($success && empty($errors)) {
        header("Location: profile.php?msg=updated");
        exit;
    }
}

include "../includes/admin_sidebar.php";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>My Profile</title>
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
        }

        h2 {
            color: #1976d2;
            margin-bottom: 20px;
            font-weight: 500;
            text-align: center;
        }

        .profile-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            margin-bottom: 20px;
        }

        .card-header {
            background: #1976d2;
            color: white;
            padding: 16px 20px;
            font-size: 1.2rem;
            font-weight: 600;
        }

        .card-body {
            padding: 24px;
        }

        .form-group {
            margin-bottom: 18px;
        }

        label {
            display: block;
            font-weight: 600;
            margin-bottom: 6px;
            color: #444;
            font-size: 0.95rem;
        }

        input[type="text"],
        input[type="email"],
        input[type="date"],
        input[type="password"],
        select {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 1rem;
            box-sizing: border-box;
            transition: border 0.3s ease;
        }

        /* Validation Styles */
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
            z-index: 10;
        }

        .btn {
            background: #1976d2;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            margin-top: 10px;
            transition: background 0.3s ease;
        }

        .btn:hover {
            background: #1259a7;
        }

        .alert {
            background: #d4edda;
            color: #155724;
            padding: 12px 18px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-weight: 500;
            border: 1px solid #c3e6cb;
        }

        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 10px;
            border: 1px solid #f5c6cb;
            border-radius: 6px;
            margin-bottom: 15px;
            font-size: 0.95rem;
        }

        .error ul {
            margin: 0;
            padding-left: 20px;
        }

        .row {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }

        .col {
            flex: 1;
            min-width: 250px;
        }

        @media (max-width: 600px) {
            .container {
                padding: 10px;
            }

            .row {
                flex-direction: column;
            }

            .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
<?php include "../includes/admin_sidebar.php"; ?>

<div class="container">
    <h2>My Profile</h2>

    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'updated'): ?>
        <div class="alert">
            ✅ Profile updated successfully!
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="error">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="profile-card">
        <div class="card-header">
            Personal Information
        </div>
        <div class="card-body">
            <form method="post" id="profileForm">
                <div class="row">
                    <div class="col">
                        <div class="form-group">
                            <label>Full Name</label>
                            <input type="text" name="fullname" required value="<?= htmlspecialchars($user['fullname']) ?>" id="fullname" />
                        </div>

                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" name="email" required value="<?= htmlspecialchars($user['email']) ?>" readonly />
                        </div>

                        <div class="form-group">
                            <label>Contact Number</label>
                            <input 
                                type="text" 
                                name="contact" 
                                value="<?= htmlspecialchars($user['contact']) ?>" 
                                placeholder="09123456789" 
                                maxlength="11" 
                                id="contact" 
                                oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                            />
                            <small style="color:#666;">11 digits, starts with 09 (e.g., 09123456789)</small>
                        </div>

                        <div class="form-group">
                            <label>Birthday</label>
                            <input type="date" name="birthday" value="<?= htmlspecialchars($user['birthday']) ?>" max="<?= date('Y-m-d') ?>" required id="birthday" />
                        </div>
                    </div>

                    <div class="col">
                        <div class="form-group">
                            <label>Username</label>
                            <input type="text" name="username" required value="<?= htmlspecialchars($user['username']) ?>" id="username" />
                        </div>

                        <div class="form-group">
                            <label>Gender</label>
                            <select name="gender" required>
                                <option value="">Select...</option>
                                <option value="Male" <?= $user['gender'] === 'Male' ? 'selected' : '' ?>>Male</option>
                                <option value="Female" <?= $user['gender'] === 'Female' ? 'selected' : '' ?>>Female</option>
                                <option value="Other" <?= $user['gender'] === 'Other' ? 'selected' : '' ?>>Other</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Address</label>
                            <input type="text" name="address" value="<?= htmlspecialchars($user['address']) ?>" id="address" placeholder="Bunakan, Madridejos, Cebu" />
                            <small style="color:#666;">Format: Barangay, Municipality, Province</small>
                        </div>
                    </div>
                </div>

                <!-- Password Change -->
                <div class="form-group">
                    <h4>Password Change</h4>
                    <div class="password-container">
                        <label>Current Password</label>
                        <input type="password" name="current_password" id="current_password" placeholder="Enter current password" />
                        <span class="toggle-password" onclick="togglePassword('current_password')">
                            <i class="fas fa-eye"></i>
                        </span>
                    </div>

                    <div class="password-container">
                        <label>New Password</label>
                        <input type="password" name="new_password" id="new_password" placeholder="Enter new password" />
                        <span class="toggle-password" onclick="togglePassword('new_password')">
                            <i class="fas fa-eye"></i>
                        </span>
                    </div>

                    <div class="password-container">
                        <label>Confirm New Password</label>
                        <input type="password" name="confirm_password" id="confirm_password" placeholder="Confirm new password" />
                        <span class="toggle-password" onclick="togglePassword('confirm_password')">
                            <i class="fas fa-eye"></i>
                        </span>
                    </div>
                </div>

                <button type="submit" class="btn">Update Profile</button>
            </form>
        </div>
    </div>
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
    fullname: (v) => {
        // Accepts: First Middle Last [Suffix]
        const pattern = /^[A-Za-z]+(?:\s+[A-Za-z.]+)?\s+[A-Za-z]+(?:\s*(?:Jr\.?|Sr\.?|II|III|IV))?$/;
        return pattern.test(v.trim());
    },
    username: (v) => /^[a-zA-Z0-9_-]{3,}$/.test(v),
    contact: (v) => /^\d{11}$/.test(v.replace(/\D/g, '')) && /^09/.test(v),
    birthday: (v) => {
        if (!v) return false;
        const birth = new Date(v);
        const today = new Date();
        const age = today.getFullYear() - birth.getFullYear();
        return birth <= today && age >= 18;
    },
    address: (v) => {
        if (!v) return false;
        return /^[a-zA-Z\s]+,\s*[a-zA-Z\s]+,\s*[a-zA-Z\s]+$/.test(v);
    },
    new_password: (v) => v.length >= 8 && /[a-zA-Z]/.test(v) && /\d/.test(v),
    confirm_password: function(v) {
        return v === document.getElementById('new_password').value && v !== '';
    }
};

// Attach validation
document.getElementById('fullname').addEventListener('blur', function() {
    validateField(this, validators.fullname);
});
document.getElementById('username').addEventListener('blur', function() {
    validateField(this, validators.username);
});
document.getElementById('contact').addEventListener('input', function() {
    this.value = this.value.replace(/[^0-9]/g, '');
    validateField(this, validators.contact);
});
document.getElementById('birthday').addEventListener('change', function() {
    validateField(this, validators.birthday);
});
document.getElementById('address').addEventListener('blur', function() {
    validateField(this, validators.address);
});
document.getElementById('new_password').addEventListener('input', function() {
    validateField(this, validators.new_password);
    const confirm = document.getElementById('confirm_password');
    if (confirm.value) validateField(confirm, validators.confirm_password);
});
document.getElementById('confirm_password').addEventListener('input', function() {
    validateField(this, validators.confirm_password);
});

// Validate all on submit
document.getElementById('profileForm').addEventListener('submit', function(e) {
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