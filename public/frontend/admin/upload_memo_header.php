<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
include "../includes/admin_sidebar.php";
// Must be logged in
if (!isset($_SESSION['user_id'])) {
    die("You are not logged in.");
}

$user_id = $_SESSION['user_id'];
$upload_dir = "../uploads/headers/";
$allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

// Create upload directory if not exists
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// 🔹 Step 1: Get user role from `users` table
$stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user_data = $result->fetch_assoc();

if (!$user_data) {
    die("User not found.");
}

// ✅ Use role as department_id (string)
$auto_department_id = $user_data['role']; // e.g., 'admin', 'school_counselor'

// 🔹 Step 2: Load current header settings
$stmt = $conn->prepare("SELECT * FROM memo_header_settings WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$settings = $result->fetch_assoc();

// Initialize if no settings
if (!$settings) {
    $settings = [
        'user_id' => $user_id,
        'header_line1'   => null,
        'header_line2'   => null,
        'header_title'   => null,
        'header_school'  => null,
        'header_address' => null,
        'header_office'  => null,
        'header_logo'    => null,
        'header_seal'    => null,
        'department_id'  => $auto_department_id
    ];
} else {
    // Ensure department_id reflects current role
    $settings['department_id'] = $auto_department_id;
}

$msg = null;

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Sanitize inputs
    $header_line1   = trim($_POST["header_line1"] ?? '');
    $header_line2   = trim($_POST["header_line2"] ?? '');
    $header_title   = trim($_POST["header_title"] ?? '');
    $header_school  = trim($_POST["header_school"] ?? '');
    $header_address = trim($_POST["header_address"] ?? '');
    $header_office  = trim($_POST["header_office"] ?? '');

    // Validate required fields
    if (empty($header_school) || empty($header_office)) {
        $msg = "School Name and Office are required.";
    } else {
        // Handle logo upload
        $header_logo = $settings['header_logo'] ?? null;
        if (!empty($_FILES['header_logo']['name']) && $_FILES['header_logo']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES["header_logo"]["name"], PATHINFO_EXTENSION));
            if (in_array($ext, $allowed_exts)) {
                // Delete old logo
                if ($header_logo && file_exists($upload_dir . $header_logo)) {
                    unlink($upload_dir . $header_logo);
                }
                $filename = "logo_user{$user_id}_" . uniqid() . ".{$ext}";
                if (move_uploaded_file($_FILES["header_logo"]["tmp_name"], $upload_dir . $filename)) {
                    $header_logo = $filename;
                }
            } else {
                $msg = "Invalid logo format. Only JPG, PNG, GIF, WEBP allowed.";
            }
        }

        // Handle seal upload
        $header_seal = $settings['header_seal'] ?? null;
        if (!empty($_FILES['header_seal']['name']) && $_FILES['header_seal']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES["header_seal"]["name"], PATHINFO_EXTENSION));
            if (in_array($ext, $allowed_exts)) {
                // Delete old seal
                if ($header_seal && file_exists($upload_dir . $header_seal)) {
                    unlink($upload_dir . $header_seal);
                }
                $filename = "seal_user{$user_id}_" . uniqid() . ".{$ext}";
                if (move_uploaded_file($_FILES["header_seal"]["tmp_name"], $upload_dir . $filename)) {
                    $header_seal = $filename;
                }
            } else {
                $msg = "Invalid seal format. Only JPG, PNG, GIF, WEBP allowed.";
            }
        }

        // Final department_id = user's role
        $final_department_id = $auto_department_id;

        // Save or update using ON DUPLICATE KEY UPDATE
        $stmt = $conn->prepare("
            INSERT INTO memo_header_settings 
            (user_id, header_line1, header_line2, header_title, header_school, 
             header_address, header_office, header_logo, header_seal, department_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
            header_line1 = VALUES(header_line1),
            header_line2 = VALUES(header_line2),
            header_title = VALUES(header_title),
            header_school = VALUES(header_school),
            header_address = VALUES(header_address),
            header_office = VALUES(header_office),
            header_logo = VALUES(header_logo),
            header_seal = VALUES(header_seal),
            department_id = VALUES(department_id)
        ");

        $stmt->bind_param(
            "isssssssss",
            $user_id,
            $header_line1,
            $header_line2,
            $header_title,
            $header_school,
            $header_address,
            $header_office,
            $header_logo,
            $header_seal,
            $final_department_id
        );

        if ($stmt->execute()) {
            $msg = "Your personal header has been successfully saved!";
            // Reload settings after save
            $stmt_load = $conn->prepare("SELECT * FROM memo_header_settings WHERE user_id = ?");
            $stmt_load->bind_param("i", $user_id);
            $stmt_load->execute();
            $result = $stmt_load->get_result();
            $settings = $result->fetch_assoc() ?: [];
            $settings['department_id'] = $final_department_id;
        } else {
            $msg = "Database error: " . $stmt->error;
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Your Header</title>
    <link rel="stylesheet" href="../includes/user_style.css">
    <style>
        .container { max-width: 600px; margin: 2rem auto; padding: 20px; }
        fieldset { margin-bottom: 20px; border: 1px solid #ccc; padding: 15px; }
        legend { font-weight: bold; color: #333; }
        label { display: block; margin-top: 10px; font-weight: 500; }
        input[type="text"], input[type="file"] { width: 100%; padding: 8px; margin-top: 5px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        .btn { padding: 10px 15px; background: #007cba; color: white; border: none; cursor: pointer; text-decoration: none; display: inline-block; margin-top: 15px; border-radius: 4px; }
        .btn.secondary { background: #6c757d; color: white; }
        .preview-img { max-width: 100px; max-height: 100px; margin-top: 5px; border: 1px solid #ddd; padding: 4px; background: #f9f9f9; }
        .alert { padding: 10px; margin: 10px 0; border-radius: 4px; }
        .alert.success { background: #d4edda; color: #155724; }
        .alert.error { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
<div class="container">
    <h2>Edit Your Memorandum Header</h2>

    <?php if ($msg): ?>
        <div class="alert <?= strpos($msg, 'error') !== false ? 'error' : 'success' ?>">
            <b><?= htmlspecialchars($msg) ?></b>
        </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">
        <fieldset>
            <legend>Your Header Info</legend>
            <label for="header_line1">Top Line 1:</label>
            <input type="text" name="header_line1" id="header_line1"
                   value="<?= htmlspecialchars($settings['header_line1'] ?? '') ?>">

            <label for="header_line2">Top Line 2:</label>
            <input type="text" name="header_line2" id="header_line2"
                   value="<?= htmlspecialchars($settings['header_line2'] ?? '') ?>">

            <label for="header_title">Title:</label>
            <input type="text" name="header_title" id="header_title"
                   value="<?= htmlspecialchars($settings['header_title'] ?? '') ?>">

            <label for="header_school">School/Office Name:</label>
            <input type="text" name="header_school" id="header_school"
                   value="<?= htmlspecialchars($settings['header_school'] ?? '') ?>" required>

            <label for="header_address">Address:</label>
            <input type="text" name="header_address" id="header_address"
                   value="<?= htmlspecialchars($settings['header_address'] ?? '') ?>">

            <label for="header_office">Office:</label>
            <input type="text" name="header_office" id="header_office"
                   value="<?= htmlspecialchars($settings['header_office'] ?? '') ?>" required>
        </fieldset>

        <fieldset>
            <legend>Your Logo & Seal</legend>
            <label for="header_logo">Logo (Left):</label>
            <input type="file" name="header_logo" id="header_logo" accept="image/*">
            <img src="<?= !empty($settings['header_logo']) ? htmlspecialchars($upload_dir . $settings['header_logo']) : '../path/to/default_logo.png' ?>"
                 id="logoPreview" alt="Logo Preview" class="preview-img">

            <label for="header_seal">Seal (Right):</label>
            <input type="file" name="header_seal" id="header_seal" accept="image/*">
            <img src="<?= !empty($settings['header_seal']) ? htmlspecialchars($upload_dir . $settings['header_seal']) : '../path/to/default_seal.png' ?>"
                 id="sealPreview" alt="Seal Preview" class="preview-img">
        </fieldset>

        <button type="submit" class="btn">Save Header</button>
        <a href="dashboard.php" class="btn secondary">Back</a>
    </form>

    <!-- Debug info 
    <p><small><strong>Debug:</strong> User ID: <?= $user_id ?> | Role (department_id): <?= htmlspecialchars($auto_department_id) ?></small></p> -->
</div>

<script>
// Live preview for logo
document.getElementById('header_logo').addEventListener('change', function(e) {
    if (e.target.files[0]) {
        document.getElementById('logoPreview').src = URL.createObjectURL(e.target.files[0]);
    }
});

// Live preview for seal
document.getElementById('header_seal').addEventListener('change', function(e) {
    if (e.target.files[0]) {
        document.getElementById('sealPreview').src = URL.createObjectURL(e.target.files[0]);
    }
});
</script>

<?php include "../includes/admin_footer.php"; ?>
</body>
</html>