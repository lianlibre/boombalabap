<?php
require_once "../includes/db.php";
require_once "../includes/auth.php";

// Must be logged in
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    die("You are not logged in.");
}

// Define upload directories using absolute path from this file's location
$base_dir = __DIR__ . '/../'; // points to public/frontend/
$upload_dir = $base_dir . 'uploads/headers/';
$signature_dir = $base_dir . 'uploads/signatures/';

// Create directories if they don't exist
if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
if (!is_dir($signature_dir)) mkdir($signature_dir, 0755, true);

// Allowed file types
$allowed_types = ['image/png', 'image/jpeg', 'image/jpg', 'image/gif'];

// 🔹 Get user role
$stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user_data = $result->fetch_assoc();
$stmt->close();

if (!$user_data || empty($user_data['role'])) {
    die("Your account has no role assigned.");
}
$user_role = trim(strtolower($user_data['role'])); // Normalize to lowercase

// 🔹 Role-to-prefix mapping
$prefix_mapping = [
    'dept_head_bsit' => 'IT',
    'dept_head_bsba' => 'BSBA',
    'dept_head_bshm' => 'HM',
    'dept_head_beed' => 'BEED',
    'admin' => 'NO',
    'soa' => 'SOA',
    'guidance' => 'GUIDANCE',
    'library' => 'LIBRARY',
    'registrar' => 'REG',
    'faculty' => 'FAC',
    'instructor' => 'INST'
];
$prefix = $prefix_mapping[$user_role] ?? 'NO'; // default to 'NO'

// 🔹 Fetch header settings for the logged-in user (by user_id)
$stmt = $conn->prepare("SELECT * FROM memo_header_settings WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$header_settings = $result->fetch_assoc() ?: [];
$stmt->close();

// 🔹 Set defaults (if no custom header exists)
$header_line1   = $header_settings['header_line1']   ?? 'Republic of the Philippines';
$header_line2   = $header_settings['header_line2']   ?? 'Region VII, Central Visayas';
$header_title   = $header_settings['header_title']   ?? 'Municipality of Madridejos';
$header_school  = $header_settings['header_school']  ?? 'MADRIDEJOS COMMUNITY COLLEGE';
$header_address = $header_settings['header_address'] ?? 'Crossing Bunakan, Madridejos, Cebu';
$header_office  = $header_settings['header_office']  ?? 'OFFICE OF THE COLLEGE PRESIDENT';
$header_logo    = $header_settings['header_logo']    ?? 'default_logo.png';
$header_seal    = $header_settings['header_seal']    ?? 'default_seal.png';

// 🔹 Get next memo number
$preview_result = $conn->query("SELECT MAX(memo_number) AS max_num FROM memos");
$preview_row = $preview_result->fetch_assoc();
$next_memo_number = $preview_row['max_num'] ? $preview_row['max_num'] + 1 : 1;
$preview_memo_number_str = sprintf('%03d', $next_memo_number);
$full_memo_number_preview = "$prefix. $preview_memo_number_str Series of " . date('Y');

// 🔹 Map student roles to display names
$role_to_display = [
    'student_bsit' => 'Student - BSIT',
    'student_bshm' => 'Student - BSHM',
    'student_bsba' => 'Student - BSBA',
    'student_bsed' => 'Student - BSED',
    'student_beed' => 'Student - BEED'
];

// 🔹 All possible recipients
$all_recipients = [
    'Office of the College President',
    'Office of the Registrar',
    'Office of the Student Affairs',
    'Finance Office',
    'BSIT Department',
    'BSBA Department',
    'BEED Department',
    'HM Department',
    'Library',
    'Guidance Office',
    'School Counselor',
    'Faculty',
    'Instructors',
    'DEPT HEADS',
    'Non Teaching Personnel',
    'UTILITY',
    'GUARD',
    'Students',
    'All Departments',
    'All Personnel'
];

foreach ($role_to_display as $display_name) {
    $all_recipients[] = $display_name;
}

// 🔹 Allowed recipients per role
$role_allowed_recipients = [
    'admin' => $all_recipients,
    'soa' => ['Office of the Registrar', 'Office of the Student Affairs', 'Library', 'Guidance Office', 'School Counselor', 'Students', 'All Departments'],
    'vp_academic' => ['BSIT Department', 'BSBA Department', 'BEED Department', 'HM Department', 'Faculty', 'Instructors', 'Students', 'All Departments'],
    'dept_head_bsit' => ['BSIT Department', 'Faculty', 'Instructors', 'Student - BSIT'],
    'dept_head_bsba' => ['BSBA Department', 'Faculty', 'Instructors', 'Student - BSBA'],
    'dept_head_bshm' => ['HM Department', 'Faculty', 'Instructors', 'Student - BSHM'],
    'dept_head_beed' => ['BEED Department', 'Faculty', 'Instructors', 'Student - BEED'],
    'faculty' => ['Student - BSIT', 'Student - BSHM', 'Student - BSBA', 'Student - BSED', 'Student - BEED'],
    'instructor' => ['Student - BSIT', 'Student - BSHM', 'Student - BSBA', 'Student - BSED', 'Student - BEED'],
    'registrar' => ['Office of the Registrar', 'Students'],
    'guidance' => ['Guidance Office', 'Students'],
    'library' => ['Library', 'Students'],
    '' => ['All Departments']
];

$allowed_recipients = $role_allowed_recipients[$user_role] ?? ['All Departments'];

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $to = $_POST["to"] ?? [];
    $to = array_unique(array_filter(array_map('trim', $to)));
    $to_string = !empty($to) ? implode(", ", $to) : "All Departments";

    $from = $header_office;
    $subject = trim($_POST["subject"]);
    $body = trim($_POST["body"]);

    // Process signatories
    $signed_by_list = $_POST['signed_by'] ?? [];
    $sign_position_list = $_POST['sign_position'] ?? [];
    $sign_org_list = $_POST['sign_org'] ?? [];
    $signature_images = $_POST['signature_image'] ?? [];

    $signatories = [];
    $valid_signers = 0;

    for ($i = 0; $i < count($signed_by_list); $i++) {
        $name = trim($signed_by_list[$i]);
        $position = trim($sign_position_list[$i]);
        $org = trim($sign_org_list[$i]);
        $imgData = $signature_images[$i] ?? '';

        if (empty($name) || empty($position) || empty($org)) continue;

        $imgPath = '';
        if ($imgData && preg_match('/^data:image\/(\w+);base64,/', $imgData)) {
            $data = substr($imgData, strpos($imgData, ',') + 1);
            $data = base64_decode($data);
            if ($data !== false) {
                $imgPath = 'sig_' . uniqid() . '.png';
                file_put_contents($signature_dir . $imgPath, $data);
            }
        }

        $signatories[] = [
            'name' => $name,
            'position' => $position,
            'org' => $org,
            'signature' => $imgPath
        ];
        $valid_signers++;
    }

    if ($valid_signers == 0) {
        $_SESSION['error'] = "At least one valid signatory is required.";
        header("Location: memo_add.php");
        exit;
    }

    // Handle logo/seal uploads
    $logo_filename = $header_logo;
    $seal_filename = $header_seal;

    if (isset($_FILES['logo']) && $_FILES['logo']['error'] == 0) {
        if (!in_array($_FILES['logo']['type'], $allowed_types)) {
            $_SESSION['error'] = "Invalid logo file type.";
            header("Location: memo_add.php");
            exit;
        }
        $ext = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
        $logo_filename = 'logo_' . uniqid() . '.' . $ext;
        move_uploaded_file($_FILES['logo']['tmp_name'], $upload_dir . $logo_filename);
    }

    if (isset($_FILES['seal']) && $_FILES['seal']['error'] == 0) {
        if (!in_array($_FILES['seal']['type'], $allowed_types)) {
            $_SESSION['error'] = "Invalid seal file type.";
            header("Location: memo_add.php");
            exit;
        }
        $ext = pathinfo($_FILES['seal']['name'], PATHINFO_EXTENSION);
        $seal_filename = 'seal_' . uniqid() . '.' . $ext;
        move_uploaded_file($_FILES['seal']['tmp_name'], $upload_dir . $seal_filename);
    }

    // Final memo number
    $result = $conn->query("SELECT MAX(memo_number) AS max_num FROM memos");
    $row = $result->fetch_assoc();
    $next_memo_number = $row['max_num'] ? $row['max_num'] + 1 : 1;

    // Insert memo
    $stmt = $conn->prepare("
        INSERT INTO memos 
        (memo_number, `from`, `to`, subject, body, 
         header_line1, header_line2, header_title, header_school, header_address, 
         header_office, header_logo, header_seal,
         signed_by, sign_position, sign_org, signature_data, user_id) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $first_signatory = $signatories[0];
    $signature_json = json_encode($signatories, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    $stmt->bind_param(
        "issssssssssssssssi",
        $next_memo_number,
        $from,
        $to_string,
        $subject,
        $body,
        $header_line1,
        $header_line2,
        $header_title,
        $header_school,
        $header_address,
        $header_office,
        $logo_filename,
        $seal_filename,
        $first_signatory['name'],
        $first_signatory['position'],
        $first_signatory['org'],
        $signature_json,
        $user_id
    );

    if ($stmt->execute()) {
        $memo_id = $conn->insert_id;
        $stmt->close();

        // Save recipients
        foreach ($to as $dept) {
            $stmt2 = $conn->prepare("INSERT INTO memo_recipients (memo_id, department) VALUES (?, ?)");
            $stmt2->bind_param("is", $memo_id, $dept);
            $stmt2->execute();
            $stmt2->close();
        }

        // Log activity
        $action = "Created memo";
        $details = "Subject: $subject";
        $log_stmt = $conn->prepare("INSERT INTO activity_log (user_id, action, memo_id, details) VALUES (?, ?, ?, ?)");
        $log_stmt->bind_param("isis", $user_id, $action, $memo_id, $details);
        $log_stmt->execute();
        $log_stmt->close();

        $_SESSION['msg'] = "added";
        header("Location: memos.php?msg=added&memo_number=" . sprintf('%03d', $next_memo_number));
        exit;
    } else {
        $_SESSION['error'] = "Database error.";
    }
}

include "../includes/admin_sidebar.php";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>MCC MEMO GEN</title>

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500&display=swap" rel="stylesheet">

    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />

    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

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
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
        }

        h2 {
            text-align: center;
            color: #2c3e50;
            margin-bottom: 20px;
        }

        form {
            background: none;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        label {
            display: block;
            margin-top: 12px;
            font-weight: 500;
            color: #2c3e50;
        }

        input[type="text"], textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            transition: border 0.3s ease;
        }

        input:focus, textarea:focus {
            border-color: #3498db;
            outline: none;
            box-shadow: 0 0 5px rgba(52, 152, 219, 0.2);
        }

        textarea {
            min-height: 120px;
            resize: vertical;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 16px;
            border: none;
            border-radius: 6px;
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

        /* Signatory Block */
        .signatory-item {
            background: #fdfdfd;
            border: 1px solid #eee;
            border-radius: 8px;
            padding: 15px;
            margin-top: 10px;
            position: relative;
        }

        .btn-remove-signatory {
            position: absolute;
            top: 10px;
            right: 10px;
            background: #e74c3c;
            color: white;
            border: none;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            font-size: 14px;
            line-height: 1;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .signature-canvas {
            width: 100%;
            max-width: 300px;
            height: 100px;
            border: 1px dotted #ccc;
            background: #fff;
            display: block;
            margin: 8px 0;
        }

        .clear-canvas {
            padding: 6px 10px;
            background: #ecf0f1;
            border: 1px solid #bdc3c7;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.6);
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
            width: 90%;
            max-width: 800px;
            overflow: hidden;
        }

        .modal-header {
            background: #2c3e50;
            color: white;
            padding: 15px 20px;
            font-size: 1.2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .close-modal {
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
        }

        .modal-body {
            padding: 20px;
            max-height: 80vh;
            overflow-y: auto;
        }

        .modal-footer {
            padding: 15px 20px;
            text-align: center;
            border-top: 1px solid #eee;
            display: flex;
            justify-content: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        /* Memo Preview */
        .memo-main {
            width: 100%;
            max-width: 760px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            font-family: 'Times New Roman', serif;
            line-height: 1.5;
            color: #000;
        }

        .header-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            gap: 20px;
        }

        .logo, .seal {
            width: 100px;
            height: 100px;
            object-fit: contain;
        }

        .center-header {
            text-align: center;
            flex: 1;
        }

        .center-header div {
            margin: 2px 0;
            font-size: 12px;
        }

        .school-name {
            font-size: 14px;
            font-weight: bold;
            margin: 4px 0;
        }

        .header-line {
            border-top: 1px solid black;
            margin: 10px 0;
        }

        .office-name {
            text-align: center;
            font-weight: bold;
            font-size: 14px;
            margin: 15px 0;
            text-transform: uppercase;
        }

        .signature-img {
            max-width: 200px;
            height: auto;
            border-bottom: 1px solid #000;
        }

        @media (max-width: 600px) {
            .container { margin: 10px; padding: 15px; }
            .header-container { flex-direction: column; text-align: center; }
            .logo, .seal { width: 80px; height: 80px; }
            .memo-main { padding: 20px; }
            .modal-content { width: 95%; }
        }
    </style>
</head>
<body>
<div class="container">
    <h2><i class="fas fa-file-alt"></i> Add Memorandum</h2>

    <?php if (isset($_SESSION['error'])): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({ icon: 'error', title: 'Error!', text: '<?= addslashes($_SESSION['error']) ?>' });
                <?php unset($_SESSION['error']); ?>
            });
        </script>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" autocomplete="off" id="memoForm">
        <label for="to"><i class="fas fa-users"></i> Recipients:</label>
        <select id="to" name="to[]" multiple="multiple" style="width:100%;">
            <?php foreach ($allowed_recipients as $recipient): ?>
                <option value="<?= htmlspecialchars($recipient) ?>"><?= htmlspecialchars($recipient) ?></option>
            <?php endforeach; ?>
        </select>

        <label for="from"><i class="fas fa-building"></i> From:</label>
        <input type="text" id="from" value="<?= htmlspecialchars($header_office) ?>" readonly>

        <label for="subject"><i class="fas fa-heading"></i> Subject:</label>
        <input type="text" id="subject" name="subject" required placeholder="Enter subject...">

        <label for="body"><i class="fas fa-pen"></i> Body:</label>
        <textarea id="body" name="body" required placeholder="Enter memo content...">Enter memo body here...</textarea>

        <div class="form-group">
            <label><i class="fas fa-image"></i> Upload Logo (optional):</label>
            <input type="file" id="logo" name="logo" accept="image/*">
        </div>

        <div class="form-group">
            <label><i class="fas fa-stamp"></i> Upload Seal (optional):</label>
            <input type="file" id="seal" name="seal" accept="image/*">
        </div>

        <!-- Signatories -->
        <div class="form-group">
            <label><i class="fas fa-signature"></i> Signatories:</label>
            <div id="signatories-container">
                <div class="signatory-item">
                    <button type="button" class="btn-remove-signatory">×</button>
                    <div class="form-group">
                        <label>Signed By:</label>
                        <input type="text" name="signed_by[]" class="signed-by-input" required>
                    </div>
                    <div class="form-group">
                        <label>Position:</label>
                        <input type="text" name="sign_position[]" class="sign-position-input" required>
                    </div>
                    <div class="form-group">
                        <label>Organization:</label>
                        <input type="text" name="sign_org[]" class="sign-org-input" required>
                    </div>
                    <div class="form-group">
                        <label>Digital Signature:</label>
                        <canvas class="signature-canvas" width="300" height="100"></canvas>
                        <button type="button" class="clear-canvas">Clear</button>
                        <input type="hidden" name="signature_image[]" class="signature-data">
                    </div>
                </div>
            </div>
            <button type="button" id="add-signatory" class="btn btn-primary mt-2">
                <i class="fas fa-plus"></i> Add Signatory
            </button>
        </div>

        <div class="btn-container" style="margin-top: 20px; text-align: center;">
            <button type="button" class="btn btn-primary" onclick="openPreviewModal()">
                <i class="fas fa-eye"></i> Preview & Send
            </button>
            <a href="memos.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Cancel
            </a>
        </div>
    </form>
</div>

<!-- ==================== MODAL PREVIEW ==================== -->
<div id="previewModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <span>Memo Preview</span>
            <button class="close-modal" onclick="document.getElementById('previewModal').style.display='none';">&times;</button>
        </div>
        <div class="modal-body">
            <div class="memo-main">
                <div class="header-container">
                    <img src="../uploads/headers/<?= htmlspecialchars($header_logo) ?>" alt="Logo" class="logo">
                    <div class="center-header">
                        <div><?= htmlspecialchars($header_line1) ?></div>
                        <div><?= htmlspecialchars($header_line2) ?></div>
                        <div><?= htmlspecialchars($header_title) ?></div>
                        <div class="school-name"><?= htmlspecialchars($header_school) ?></div>
                        <div><?= htmlspecialchars($header_address) ?></div>
                    </div>
                    <img src="../uploads/headers/<?= htmlspecialchars($header_seal) ?>" alt="Seal" class="seal">
                </div>
                <hr class="header-line">
                <div class="office-name"><?= htmlspecialchars($header_office) ?></div>
                <div class="memo-number-date"><strong>MEMORANDUM ORDER</strong><br><?= htmlspecialchars($full_memo_number_preview) ?><br><span id="datePreview"><?= date('F j, Y') ?></span></div>
                <div class="memo-meta"><div><label>TO:</label> <span id="toPreview"></span></div><div><label>FROM:</label> <?= htmlspecialchars($header_office) ?></div></div>
                <div class="memo-subject"><label>SUBJECT:</label> <span id="subjectPreview"></span></div>
                <div class="memo-body" id="bodyPreview"></div>
                <div class="signature-block-left"><div>Signed by:</div><div id="signaturesPreview"></div></div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-primary" onclick="confirmSend()">
                <i class="fas fa-paper-plane"></i> Send Memo
            </button>
            <button type="button" class="btn btn-secondary" onclick="document.getElementById('previewModal').style.display='none';">
                <i class="fas fa-times"></i> Close
            </button>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.tiny.cloud/1/uxdub7o368aviboi5yyjizj1kgzcguypv7ud50dfv5m8unbd/tinymce/7/tinymce.min.js" referrerpolicy="origin"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
function initCanvas(canvas) {
    const ctx = canvas.getContext('2d');
    let isDrawing = false;

    function resizeCanvas() {
        const rect = canvas.getBoundingClientRect();
        canvas.width = rect.width;
        canvas.height = 100;
        ctx.lineCap = 'round';
        ctx.lineWidth = 2;
        ctx.strokeStyle = 'black';
        redraw();
    }

    function getPos(e) {
        const rect = canvas.getBoundingClientRect();
        return e.touches ? {
            x: e.touches[0].clientX - rect.left,
            y: e.touches[0].clientY - rect.top
        } : {
            x: e.clientX - rect.left,
            y: e.clientY - rect.top
        };
    }

    function start(e) {
        e.preventDefault();
        isDrawing = true;
        const pos = getPos(e);
        ctx.beginPath();
        ctx.moveTo(pos.x, pos.y);
    }

    function draw(e) {
        if (!isDrawing) return;
        e.preventDefault();
        const pos = getPos(e);
        ctx.lineTo(pos.x, pos.y);
        ctx.stroke();
    }

    function end() {
        isDrawing = false;
        updateInput();
        memoPreviewUpdate();
    }

    function updateInput() {
        const input = canvas.closest('.signatory-item')?.querySelector('.signature-data');
        if (input) input.value = canvas.toDataURL('image/png');
    }

    function clear() {
        ctx.fillStyle = 'white';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        updateInput();
        memoPreviewUpdate();
    }

    function redraw() {
        ctx.fillStyle = 'white';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        const imgData = canvas.closest('.signatory-item')?.querySelector('.signature-data')?.value;
        if (imgData) {
            const img = new Image();
            img.onload = () => ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
            img.src = imgData;
        }
    }

    window.addEventListener('resize', resizeCanvas);
    resizeCanvas();

    canvas.addEventListener('mousedown', start);
    canvas.addEventListener('mousemove', draw);
    canvas.addEventListener('mouseup', end);
    canvas.addEventListener('mouseout', end);

    canvas.addEventListener('touchstart', start, { passive: false });
    canvas.addEventListener('touchmove', draw, { passive: false });
    canvas.addEventListener('touchend', end);

    const clearBtn = canvas.closest('.signatory-item')?.querySelector('.clear-canvas');
    if (clearBtn) clearBtn.onclick = clear;
}
</script>

<script>
document.addEventListener('DOMContentLoaded', () => {
    initCanvas(document.querySelector('#signatories-container .signature-canvas'));

    $('#to').select2({
        placeholder: "Select recipients...",
        allowClear: true,
        width: '100%'
    }).on('change', memoPreviewUpdate);

    tinymce.init({
        selector: 'textarea#body',
        height: 300,
        menubar: false,
        branding: false,
        setup: editor => editor.on('input', memoPreviewUpdate)
    });

    memoPreviewUpdate();
});
</script>

<script>
document.getElementById('add-signatory').addEventListener('click', () => {
    if (document.querySelectorAll('.signatory-item').length >= 10) {
        Swal.fire({ icon: 'warning', title: 'Limit Reached', text: 'Maximum 10 signatories allowed.' });
        return;
    }

    const container = document.getElementById('signatories-container');
    const item = document.createElement('div');
    item.className = 'signatory-item';
    item.innerHTML = `
        <button type="button" class="btn-remove-signatory">×</button>
        <div class="form-group">
            <label>Signed By:</label>
            <input type="text" name="signed_by[]" class="signed-by-input" required>
        </div>
        <div class="form-group">
            <label>Position:</label>
            <input type="text" name="sign_position[]" class="sign-position-input" required>
        </div>
        <div class="form-group">
            <label>Organization:</label>
            <input type="text" name="sign_org[]" class="sign-org-input" required>
        </div>
        <div class="form-group">
            <label>Digital Signature:</label>
            <canvas class="signature-canvas" width="300" height="100"></canvas>
            <button type="button" class="clear-canvas">Clear</button>
            <input type="hidden" name="signature_image[]" class="signature-data">
        </div>
    `;
    container.appendChild(item);
    initCanvas(item.querySelector('.signature-canvas'));

    item.querySelector('.btn-remove-signatory').onclick = () => {
        if (container.children.length > 1) {
            item.remove();
            memoPreviewUpdate();
        } else {
            Swal.fire({ icon: 'info', title: 'Info', text: 'At least one signatory is required.' });
        }
    };
});

document.addEventListener('click', e => {
    if (e.target.classList.contains('btn-remove-signatory')) {
        const item = e.target.closest('.signatory-item');
        if (document.querySelectorAll('.signatory-item').length > 1) {
            item.remove();
            memoPreviewUpdate();
        }
    }
});
</script>

<script>
function memoPreviewUpdate() {
    try {
        document.getElementById('toPreview').innerText = ($('#to').val() || []).join(", ");
        document.getElementById('subjectPreview').innerText = document.getElementById('subject')?.value || '';
        document.getElementById('bodyPreview').innerHTML = (tinymce.get('body')?.getContent() || '').replace(/\n/g, "<br>");

        const preview = document.getElementById('signaturesPreview');
        if (preview) {
            preview.innerHTML = '';
            document.querySelectorAll('.signatory-item').forEach(el => {
                const name = el.querySelector('.signed-by-input')?.value?.trim();
                if (!name) return;
                const pos = el.querySelector('.sign-position-input')?.value?.trim();
                const org = el.querySelector('.sign-org-input')?.value?.trim();
                const sig = el.querySelector('.signature-data')?.value;

                const div = document.createElement('div');
                div.className = 'signature-container';

                if (sig) {
                    const img = document.createElement('img');
                    img.src = sig;
                    img.className = 'signature-img';
                    div.appendChild(img);
                }

                const txt = document.createElement('div');
                txt.innerHTML = `<strong>${name}</strong><br>${pos || '[Position]'}<br>${org || '[Org]'}`;
                div.appendChild(txt);
                preview.appendChild(div);
            });
        }
    } catch (e) {
        console.error("Preview error:", e);
    }
}
</script>

<script>
function openPreviewModal() {
    memoPreviewUpdate();
    document.getElementById('previewModal').style.display = 'flex';
}

function confirmSend() {
    Swal.fire({
        title: 'Confirm Send?',
        text: "Are you sure you want to send this memorandum?",
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes, Send!',
        cancelButtonText: 'Cancel'
    }).then(result => {
        if (result.isConfirmed) {
            document.getElementById('memoForm').submit();
        }
    });
}

// Close modal with ESC key
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        document.getElementById('previewModal').style.display = 'none';
    }
});
</script>

<?php include "../includes/admin_footer.php"; ?>
</body>
</html>