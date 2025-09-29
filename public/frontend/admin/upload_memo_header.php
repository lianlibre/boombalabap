<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";

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
        'header_line1'   => '',
        'header_line2'   => '',
        'header_title'   => '',
        'header_school'  => '',
        'header_address' => '',
        'header_office'  => '',
        'header_logo'    => null,
        'header_seal'    => null,
        'department_id'  => $auto_department_id
    ];
} else {
    $settings['department_id'] = $auto_department_id; // Sync with current role
}

$msg_type = null; // 'success' or 'error'

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
        $msg_type = 'error';
        $msg_text = "School Name and Office are required.";
    } else {
        $header_logo = $settings['header_logo'] ?? null;
        $header_seal = $settings['header_seal'] ?? null;

        // Handle logo upload
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
                $msg_type = 'error';
                $msg_text = "Invalid logo format. Only JPG, PNG, GIF, WEBP allowed.";
            }
        }

        // Handle seal upload
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
                $msg_type = 'error';
                $msg_text = "Invalid seal format. Only JPG, PNG, GIF, WEBP allowed.";
            }
        }

        // Save or update
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
            $auto_department_id
        );

        if ($stmt->execute()) {
            $msg_type = 'success';
            $msg_text = "Your personal header has been successfully saved!";
            // Refresh settings after save
            $stmt_load = $conn->prepare("SELECT * FROM memo_header_settings WHERE user_id = ?");
            $stmt_load->bind_param("i", $user_id);
            $stmt_load->execute();
            $result = $stmt_load->get_result();
            $settings = $result->fetch_assoc() ?: [];
            $settings['department_id'] = $auto_department_id;
        } else {
            $msg_type = 'error';
            $msg_text = "Database error: " . htmlspecialchars($stmt->error);
        }
        $stmt->close();
    }

    // Store message in session to avoid re-display on refresh
    $_SESSION['msg_type'] = $msg_type;
    $_SESSION['msg_text'] = $msg_text;
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Retrieve session message
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
    <title>Edit Your Header - Admin Panel</title>

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500&display=swap" rel="stylesheet">

    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" />

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />

    <style>
        body {
            font-family: 'Roboto', Arial, sans-serif;
            background: #f4f6f9;
            margin: 0;
            padding: 0;
            color: #333;
        }

        .container {
            max-width: 700px;
            margin: 20px auto;
            background: #fff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        h2 {
            text-align: center;
            color: #2c3e50;
            margin-bottom: 25px;
            font-weight: 500;
        }

        fieldset {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 25px;
            background: #fdfdfd;
        }

        legend {
            font-weight: 600;
            color: #2980b9;
            padding: 0 10px;
            font-size: 1.1rem;
        }

        .form-row {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        .form-group {
            flex: 1 1 300px;
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 500;
            color: #2c3e50;
        }

        .form-group input[type="text"],
        .form-group input[type="file"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #bdc3c7;
            border-radius: 6px;
            font-size: 14px;
            box-sizing: border-box;
        }

        .form-group input:focus {
            border-color: #3498db;
            outline: none;
            box-shadow: 0 0 5px rgba(52, 152, 219, 0.2);
        }

        /* Image Preview */
        .img-preview-container {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 20px;
            margin-top: 15px;
        }

        .img-preview-box {
            text-align: center;
            flex: 1 1 120px;
        }

        .preview-img {
            max-width: 100px;
            max-height: 100px;
            border: 2px solid #eee;
            border-radius: 8px;
            padding: 4px;
            background: #f9f9f9;
            object-fit: contain;
        }

        .btn {
            padding: 10px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: #3498db;
            color: #fff;
        }

        .btn-primary:hover {
            background: #2980b9;
        }

        .btn-secondary {
            background: #95a5a6;
            color: #fff;
        }

        .btn-secondary:hover {
            background: #7f8c8d;
        }

        .button-group {
            text-align: center;
            margin-top: 25px;
        }

        @media (max-width: 600px) {
            .container {
                margin: 10px;
                padding: 15px;
            }

            h2 {
                font-size: 1.4rem;
            }

            fieldset {
                padding: 15px;
            }

            legend {
                font-size: 1rem;
            }

            .form-row {
                flex-direction: column;
                gap: 10px;
            }

            .img-preview-container {
                flex-direction: column;
                align-items: flex-start;
            }

            .button-group {
                text-align: center;
            }

            .btn span {
                display: none;
            }

            .btn i {
                margin: 0;
            }

            .btn {
                width: 100%;
                justify-content: center;
                margin-bottom: 8px;
            }
        }
    </style>
</head>
<body>
<div class="container">
    <h2>📝 Edit Your Memorandum Header</h2>

    <form method="post" enctype="multipart/form-data">
        <fieldset>
            <legend>Your Header Info</legend>
            <div class="form-row">
                <div class="form-group">
                    <label for="header_line1">Top Line 1:</label>
                    <input type="text" name="header_line1" id="header_line1"
                           value="<?= htmlspecialchars($settings['header_line1'] ?? '') ?>"
                           placeholder="e.g., Republic of the Philippines">
                </div>
                <div class="form-group">
                    <label for="header_line2">Top Line 2:</label>
                    <input type="text" name="header_line2" id="header_line2"
                           value="<?= htmlspecialchars($settings['header_line2'] ?? '') ?>"
                           placeholder="e.g., Department of Education">
                </div>
            </div>

            <div class="form-group">
                <label for="header_title">Title:</label>
                <input type="text" name="header_title" id="header_title"
                       value="<?= htmlspecialchars($settings['header_title'] ?? '') ?>"
                       placeholder="e.g., Office of the School Counselor">
            </div>

            <div class="form-group">
                <label for="header_school">School/Office Name <span style="color:red;">*</span>:</label>
                <input type="text" name="header_school" id="header_school"
                       value="<?= htmlspecialchars($settings['header_school'] ?? '') ?>"
                       placeholder="e.g., San Pablo City Integrated High School" required>
            </div>

            <div class="form-group">
                <label for="header_address">Address:</label>
                <input type="text" name="header_address" id="header_address"
                       value="<?= htmlspecialchars($settings['header_address'] ?? '') ?>"
                       placeholder="e.g., Brgy. San Isidro, San Pablo City">
            </div>

            <div class="form-group">
                <label for="header_office">Office <span style="color:red;">*</span>:</label>
                <input type="text" name="header_office" id="header_office"
                       value="<?= htmlspecialchars($settings['header_office'] ?? '') ?>"
                       placeholder="e.g., Guidance Office" required>
            </div>
        </fieldset>

        <fieldset>
            <legend>Your Logo & Seal</legend>
            <div class="img-preview-container">
                <div class="img-preview-box">
                    <label for="header_logo">Logo (Left):</label>
                    <input type="file" name="header_logo" id="header_logo" accept="image/*">
                    <img src="<?= !empty($settings['header_logo']) ? '../uploads/headers/' . htmlspecialchars($settings['header_logo']) : 'https://via.placeholder.com/100x100?text=Logo' ?>"
                         id="logoPreview" alt="Logo Preview" class="preview-img">
                </div>

                <div class="img-preview-box">
                    <label for="header_seal">Seal (Right):</label>
                    <input type="file" name="header_seal" id="header_seal" accept="image/*">
                    <img src="<?= !empty($settings['header_seal']) ? '../uploads/headers/' . htmlspecialchars($settings['header_seal']) : 'https://via.placeholder.com/100x100?text=Seal' ?>"
                         id="sealPreview" alt="Seal Preview" class="preview-img">
                </div>
            </div>
        </fieldset>

        <div class="button-group">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> <span>Save Header</span>
            </button>
            <a href="dashboard.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> <span>Back to Dashboard</span>
            </a>
        </div>
    </form>
</div>

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
// Show SweetAlert2 based on PHP session message
<?php if ($msg_type && $msg_text): ?>
Swal.fire({
    icon: '<?= $msg_type ?>',
    title: '<?= $msg_type === 'success' ? 'Success!' : 'Error!' ?>',
    text: '<?= addslashes($msg_text) ?>'
});
<?php endif; ?>

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