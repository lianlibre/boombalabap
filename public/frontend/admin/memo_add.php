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

// 🔹 All possible recipients (flat list)
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

// Add student entries
foreach ($role_to_display as $display_name) {
    $all_recipients[] = $display_name;
}

// 🔹 Define allowed recipients per role (flat array)
$role_allowed_recipients = [
    'admin' => $all_recipients,

    'soa' => [
        'Office of the Registrar',
        'Office of the Student Affairs',
        'Library',
        'Guidance Office',
        'School Counselor',
        'Students',
        'All Departments'
    ],
    'vp_academic' => [
        'BSIT Department',
        'BSBA Department',
        'BEED Department',
        'HM Department',
        'Faculty',
        'Instructors',
        'Students',
        'All Departments'
    ],

    'dept_head_bsit' => [
        'BSIT Department',
        'Faculty',
        'Instructors',
        'Student - BSIT'
    ],
    'dept_head_bsba' => [
        'BSBA Department',
        'Faculty',
        'Instructors',
        'Student - BSBA'
    ],
    'dept_head_bshm' => [
        'HM Department',
        'Faculty',
        'Instructors',
        'Student - BSHM'
    ],
    'dept_head_beed' => [
        'BEED Department',
        'Faculty',
        'Instructors',
        'Student - BEED'
    ],

    'faculty' => [
        'Student - BSIT',
        'Student - BSHM',
        'Student - BSBA',
        'Student - BSED',
        'Student - BEED'
    ],
    'instructor' => [
        'Student - BSIT',
        'Student - BSHM',
        'Student - BSBA',
        'Student - BSED',
        'Student - BEED'
    ],

    'registrar' => [
        'Office of the Registrar',
        'Students'
    ],
    'guidance' => [
        'Guidance Office',
        'Students'
    ],
    'library' => [
        'Library',
        'Students'
    ],

    '' => ['All Departments'] // fallback
];

// 🔹 Get allowed recipients for current user's role
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

        if (empty($name)) continue;
        if (empty($position)) continue;
        if (empty($org)) continue;

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
        die("At least one valid signatory is required.");
    }

    // Handle file uploads
    $logo_filename = $header_logo;
    $seal_filename = $header_seal;

    if (isset($_FILES['logo']) && $_FILES['logo']['error'] == 0) {
        if (!in_array($_FILES['logo']['type'], $allowed_types)) {
            die("Invalid logo file type. Only PNG, JPG, JPEG, GIF allowed.");
        }
        $ext = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
        $logo_filename = 'logo_' . uniqid() . '.' . $ext;
        $target = $upload_dir . $logo_filename;
        if (!move_uploaded_file($_FILES['logo']['tmp_name'], $target)) {
            die("Failed to save logo.");
        }
    }

    if (isset($_FILES['seal']) && $_FILES['seal']['error'] == 0) {
        if (!in_array($_FILES['seal']['type'], $allowed_types)) {
            die("Invalid seal file type. Only PNG, JPG, JPEG, GIF allowed.");
        }
        $ext = pathinfo($_FILES['seal']['name'], PATHINFO_EXTENSION);
        $seal_filename = 'seal_' . uniqid() . '.' . $ext;
        $target = $upload_dir . $seal_filename;
        if (!move_uploaded_file($_FILES['seal']['tmp_name'], $target)) {
            die("Failed to save seal.");
        }
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
         signed_by, sign_position, sign_org, signature_data, user_id, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
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

    if (!$stmt->execute()) {
        die("Database error inserting memo: " . $stmt->error);
    }

    $memo_id = $conn->insert_id;
    $stmt->close();

    // Save recipients
    foreach ($to as $dept) {
        $dept = trim($dept);
        if (empty($dept)) continue;
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

    $memo_number_str = sprintf('%03d', $next_memo_number);
    header("Location: memos.php?msg=added&memo_number=" . $memo_number_str);
    exit;
}

include "../includes/admin_sidebar.php";
?>
<!DOCTYPE html>
<html>
<head>
    <title>MCC MEMO GEN</title>
    <link rel="stylesheet" href="../includes/user_style.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        body {
            background: #f5f5f5;
            font-family: Arial, sans-serif;
            color: #000;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }

        h2 {
            color: #000;
            margin-bottom: 20px;
        }

        form {
            padding: 24px;
            border-radius: 8px;
            box-shadow: 0 1px 6px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        label {
            display: block;
            margin-top: 12px;
            font-weight: bold;
            color: #000;
        }

        input[type="text"], textarea {
            width: 100%;
            padding: 8px;
            margin-top: 6px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 14px;
            color: #000;
        }

        textarea {
            min-height: 120px;
            resize: vertical;
        }

        .form-group {
            margin-top: 12px;
        }

        .btn {
            display: inline-block;
            padding: 10px 16px;
            margin-top: 20px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        .btn:hover {
            background: #0056b3;
        }

        .btn.secondary {
            background: #6c757d;
            color: white;
        }

        .btn.secondary:hover {
            background: #5a6268;
        }

        /* ==================== MEMO PREVIEW ==================== */
        .memo-main {
            width: 760px;
            margin: 40px auto;
            background: white;
            padding: 30px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.07);
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
            flex: 1;
            text-align: center;
            margin: 0 20px;
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

        .memo-number-date {
            font-size: 14px;
            margin: 15px 0;
        }

        .memo-meta {
            margin: 15px 0;
            font-size: 14px;
        }

        .memo-meta label {
            font-weight: bold;
            min-width: 40px;
            display: inline-block;
        }

        .memo-subject {
            font-weight: bold;
            margin: 15px 0;
            font-size: 14px;
        }

        .memo-subject label {
            display: inline-block;
            margin-right: 4px;
        }

        .memo-body {
            margin: 15px 0;
            font-size: 14px;
            line-height: 1.6;
            white-space: pre-line;
        }

        .signature-block-left {
            margin-top: 50px;
            margin-left: 20px;
            font-size: 14px;
            line-height: 1.6;
            text-align: left;
        }

        .signature-container {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 4px;
            margin-top: 15px;
        }

        .signature-img {
            max-width: 200px;
            height: auto;
            border-bottom: 1px solid #000;
            margin-bottom: 2px;
        }

        .signatory-item {
            margin-bottom: 15px;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 6px;
            position: relative;
        }

        .btn-remove-signatory {
            position: absolute;
            top: 8px;
            right: 8px;
            background: red;
            color: white;
            border: none;
            width: 24px;
            height: 24px;
            font-size: 14px;
            text-align: center;
            line-height: 1;
            border-radius: 50%;
            cursor: pointer;
            padding: 0;
            font-weight: bold;
        }

        @media (max-width: 800px) {
            .memo-main {
                width: 100%;
                padding: 15px;
                margin: 10px auto;
            }
            .header-container {
                flex-direction: column;
            }
            .logo, .seal {
                margin: 10px auto;
            }
        }

        @media print {
            .no-print, .btn, .form-group {
                display: none !important;
            }
            .memo-main {
                box-shadow: none;
                margin: 0;
                padding: 0;
            }
            .signature-img {
                max-width: 150px;
            }
        }
    </style>
</head>
<body>
<div class="container">
    <h2>Add Memorandum</h2>

    <form method="post" enctype="multipart/form-data" autocomplete="off">
        <label for="to">Recipients:</label>
        <select id="to" name="to[]" multiple="multiple" style="width:100%;">
            <?php foreach ($allowed_recipients as $recipient): ?>
                <option value="<?= htmlspecialchars($recipient) ?>">
                    <?= htmlspecialchars($recipient) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label for="from">From:</label>
        <input type="text" id="from" value="<?= htmlspecialchars($header_office) ?>" readonly>

        <label for="subject">Subject:</label>
        <input type="text" id="subject" name="subject" required>

        <label for="body"><strong>Body:</strong></label>
        <textarea id="body" name="body" required>Enter memo body here...</textarea>

        <!-- File Uploads -->
        <label for="logo">Upload Logo (optional):</label>
        <input type="file" id="logo" name="logo" accept="image/png, image/jpeg, image/jpg, image/gif">

        <label for="seal">Upload Seal (optional):</label>
        <input type="file" id="seal" name="seal" accept="image/png, image/jpeg, image/jpg, image/gif">

        <!-- Signatories -->
        <div class="form-group">
            <label>Signatories:</label>
            <div id="signatories-container">
                <!-- First signatory -->
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
                        <label>Department/Organization:</label>
                        <input type="text" name="sign_org[]" class="sign-org-input" required>
                    </div>
                    <div class="form-group">
                        <label>Digital Signature (Draw below):</label>
                        <canvas class="signature-canvas" width="300" height="100" style="border: 1px dotted #aaa;"></canvas>
                        <button type="button" class="clear-canvas">Clear</button>
                        <input type="hidden" name="signature_image[]" class="signature-data">
                    </div>
                </div>
            </div>
            <button type="button" id="add-signatory" class="btn" style="margin-top: 10px;">+ Add Another Signatory</button>
        </div>

        <button type="submit" class="btn">Add Memorandum</button>
        <a href="memos.php" class="btn secondary">Cancel</a>
    </form>

    <!-- ==================== MEMO PREVIEW ==================== -->
    <div class="memo-main no-print">
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

        <div class="memo-number-date">
            <strong>MEMORANDUM ORDER</strong><br>
            <?= htmlspecialchars($full_memo_number_preview) ?><br>
            <span id="datePreview"><?= date('F j, Y') ?></span>
        </div>

        <div class="memo-meta">
            <div><label>TO:</label> <span id="toPreview"></span></div>
            <div><label>FROM:</label> <?= htmlspecialchars($header_office) ?></div>
        </div>

        <div class="memo-subject">
            <label>SUBJECT:</label>
            <span id="subjectPreview" style="display: inline-block; margin-left: 4px;"></span>
        </div>

        <div class="memo-body" id="bodyPreview"></div>

        <!-- Signature Preview -->
        <div class="signature-block-left">
            <div>Signed by:</div>
            <div id="signaturesPreview"></div>
        </div>
    </div>
</div>

<!-- Load Scripts in Correct Order -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.tiny.cloud/1/uxdub7o368aviboi5yyjizj1kgzcguypv7ud50dfv5m8unbd/tinymce/7/tinymce.min.js" referrerpolicy="origin"></script>

<script>
// Canvas Signature Handler
function initCanvas(canvas) {
    const ctx = canvas.getContext('2d');
    ctx.fillStyle = 'white';
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    ctx.strokeStyle = 'black';
    ctx.lineWidth = 2;
    ctx.lineCap = 'round';

    let isDrawing = false;

    function startDrawing(e) {
        isDrawing = true;
        const rect = canvas.getBoundingClientRect();
        const x = e.clientX - rect.left;
        const y = e.clientY - rect.top;
        ctx.beginPath();
        ctx.moveTo(x, y);
    }

    function draw(e) {
        if (!isDrawing) return;
        const rect = canvas.getBoundingClientRect();
        const x = e.clientX - rect.left;
        const y = e.clientY - rect.top;
        ctx.lineTo(x, y);
        ctx.stroke();
    }

    function stopDrawing() {
        if (isDrawing) {
            isDrawing = false;
            updateHiddenField();
            memoPreviewUpdate();
        }
    }

    function handleTouch(e) {
        const touch = e.touches[0];
        const mouseEvent = new MouseEvent(
            e.type === 'touchstart' ? 'mousedown' : 'mousemove',
            { clientX: touch.clientX, clientY: touch.clientY }
        );
        canvas.dispatchEvent(mouseEvent);
        e.preventDefault();
    }

    function updateHiddenField() {
        const hiddenInput = canvas.closest('.signatory-item')?.querySelector('.signature-data');
        if (hiddenInput) {
            hiddenInput.value = canvas.toDataURL('image/png');
        }
    }

    function clearCanvas() {
        const ctx = canvas.getContext('2d');
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        ctx.fillStyle = 'white';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        updateHiddenField();
        memoPreviewUpdate();
    }

    // Attach events
    canvas.addEventListener('mousedown', startDrawing);
    canvas.addEventListener('mousemove', draw);
    canvas.addEventListener('mouseup', stopDrawing);
    canvas.addEventListener('mouseout', stopDrawing);
    canvas.addEventListener('touchstart', handleTouch, { passive: false });
    canvas.addEventListener('touchmove', handleTouch, { passive: false });
    canvas.addEventListener('touchend', stopDrawing);

    // Clear button
    const clearBtn = canvas.closest('.signatory-item')?.querySelector('.clear-canvas');
    if (clearBtn) clearBtn.onclick = clearCanvas;
}

// Initialize ALL existing canvases on load
document.addEventListener('DOMContentLoaded', function () {
    // Init the first canvas
    const firstCanvas = document.querySelector('#signatories-container .signature-canvas');
    if (firstCanvas) initCanvas(firstCanvas);
});

// Add new signatory
document.getElementById('add-signatory').addEventListener('click', function () {
    const containerEl = document.getElementById('signatories-container');
    const items = document.querySelectorAll('.signatory-item');
    if (items.length >= 10) {
        alert("Maximum 10 signatories allowed.");
        return;
    }

    const newItem = document.createElement('div');
    newItem.className = 'signatory-item';
    newItem.innerHTML = `
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
            <label>Department/Organization:</label>
            <input type="text" name="sign_org[]" class="sign-org-input" required>
        </div>
        <div class="form-group">
            <label>Digital Signature (Draw below):</label>
            <canvas class="signature-canvas" width="300" height="100" style="border: 1px dotted #aaa;"></canvas>
            <button type="button" class="clear-canvas">Clear</button>
            <input type="hidden" name="signature_image[]" class="signature-data">
        </div>
    `;
    containerEl.appendChild(newItem);

    // ✅ Initialize canvas for new item
    initCanvas(newItem.querySelector('.signature-canvas'));

    newItem.querySelector('.btn-remove-signatory').addEventListener('click', function () {
        if (document.querySelectorAll('.signatory-item').length > 1) {
            newItem.remove();
            memoPreviewUpdate();
        } else {
            alert('At least one signatory is required.');
        }
    });
});

// Remove signatory
document.addEventListener('click', function (e) {
    if (e.target.classList.contains('btn-remove-signatory')) {
        const item = e.target.closest('.signatory-item');
        if (document.querySelectorAll('.signatory-item').length > 1) {
            item.remove();
            memoPreviewUpdate();
        } else {
            alert('At least one signatory is required.');
        }
    }
});
</script>

<script>
document.getElementById('add-signatory').addEventListener('click', function () {
    const containerEl = document.getElementById('signatories-container');
    const items = document.querySelectorAll('.signatory-item');
    if (items.length >= 10) {
        alert("Maximum 10 signatories allowed.");
        return;
    }

    const newItem = document.createElement('div');
    newItem.className = 'signatory-item';
    newItem.innerHTML = `
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
            <label>Department/Organization:</label>
            <input type="text" name="sign_org[]" class="sign-org-input" required>
        </div>
        <div class="form-group">
            <label>Digital Signature (Draw below):</label>
            <canvas class="signature-canvas" width="300" height="100" style="border: 1px dotted #aaa;"></canvas>
            <button type="button" class="clear-canvas">Clear</button>
            <input type="hidden" name="signature_image[]" class="signature-data">
        </div>
    `;
    containerEl.appendChild(newItem);

    initCanvas(newItem.querySelector('.signature-canvas'));

    newItem.querySelector('.btn-remove-signatory').addEventListener('click', function () {
        if (document.querySelectorAll('.signatory-item').length > 1) {
            newItem.remove();
            memoPreviewUpdate();
        } else {
            alert('At least one signatory is required.');
        }
    });
});

document.addEventListener('click', function (e) {
    if (e.target.classList.contains('btn-remove-signatory')) {
        const item = e.target.closest('.signatory-item');
        if (document.querySelectorAll('.signatory-item').length > 1) {
            item.remove();
            memoPreviewUpdate();
        } else {
            alert('At least one signatory is required.');
        }
    }
});
</script>

<script>
function memoPreviewUpdate() {
    try {
        const to = $('#to').val() || [];
        document.getElementById('toPreview').innerText = to.join(", ");
        document.getElementById('subjectPreview').innerText = document.getElementById('subject')?.value || '';
        const body = document.getElementById('body')?.value || '';
        document.getElementById('bodyPreview').innerHTML = body.replace(/\n/g, "<br>");

        const preview = document.getElementById('signaturesPreview');
        if (!preview) return;
        preview.innerHTML = '';

        document.querySelectorAll('.signatory-item').forEach(item => {
            const name = item.querySelector('.signed-by-input')?.value?.trim();
            const position = item.querySelector('.sign-position-input')?.value?.trim();
            const org = item.querySelector('.sign-org-input')?.value?.trim();
            const imgData = item.querySelector('.signature-data')?.value;

            if (!name) return;

            const container = document.createElement('div');
            container.className = 'signature-container';

            if (imgData) {
                const img = document.createElement('img');
                img.src = imgData;
                img.className = 'signature-img';
                container.appendChild(img);
            }

            const text = document.createElement('div');
            text.innerHTML = `<strong>${name}</strong><br>${position || '[Position]'}<br>${org || '[Department]'}`;
            container.appendChild(text);
            preview.appendChild(container);
        });

        const logoInput = document.getElementById('logo');
        if (logoInput && logoInput.files && logoInput.files[0]) {
            const reader = new FileReader();
            reader.onload = e => {
                const logoImg = document.querySelector('.logo');
                if (logoImg) logoImg.src = e.target.result;
            };
            reader.readAsDataURL(logoInput.files[0]);
        }

        const sealInput = document.getElementById('seal');
        if (sealInput && sealInput.files && sealInput.files[0]) {
            const reader = new FileReader();
            reader.onload = e => {
                const sealImg = document.querySelector('.seal');
                if (sealImg) sealImg.src = e.target.result;
            };
            reader.readAsDataURL(sealInput.files[0]);
        }
    } catch (e) {
        console.error("Error in memoPreviewUpdate:", e);
    }
}
</script>

<script>
$(document).ready(function () {
    // Initialize Select2 after DOM and jQuery are ready
    $('#to').select2({
        placeholder: "Select recipients...",
        allowClear: true,
        width: '100%',
        tags: false // Set to true to allow custom typing
    });

    // Update preview when selection changes
    $('#to').on('change', memoPreviewUpdate);
    memoPreviewUpdate(); // Initial call
});
</script>

<script>
tinymce.init({
    selector: 'textarea#body',
    height: 400,
    menubar: false,
    branding: false,
    resize: true,
    plugins: ['advlist', 'autolink', 'lists', 'link', 'image', 'charmap', 'preview', 'anchor', 'searchreplace', 'visualblocks', 'code', 'fullscreen', 'insertdatetime', 'media', 'table', 'wordcount', 'help'],
    toolbar: 'undo redo | blocks | bold italic underline | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image | code | help',
    content_style: 'body { font-family: Arial, sans-serif; font-size:14px; line-height:1.6; color: #000; }',
    setup: editor => {
        editor.on('input', () => {
            document.getElementById('body').value = editor.getContent();
            memoPreviewUpdate();
        });
    }
});

// Sync before submit
document.querySelector("form").addEventListener("submit", function () {
    if (typeof tinymce !== 'undefined' && tinymce.get('body')) {
        tinymce.get('body').save();
    }
});
</script>

<?php include "../includes/admin_footer.php"; ?>
</body>
</html>