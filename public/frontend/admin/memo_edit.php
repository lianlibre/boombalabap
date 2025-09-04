<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

// Get memo ID from URL
$memo_id = $_GET['id'] ?? null;
if (!$memo_id || !is_numeric($memo_id)) {
    die("Invalid memo ID.");
}

// Fetch memo from database
$stmt = $conn->prepare("SELECT * FROM memos WHERE id = ?");
$stmt->bind_param("i", $memo_id);
$stmt->execute();
$result = $stmt->get_result();
$memo = $result->fetch_assoc();

if (!$memo) {
    die("Memo not found.");
}

// Decode signatories
$signatories = json_decode($memo['signature_data'], true) ?: [];
if (!is_array($signatories)) $signatories = [];

// Define upload directories using absolute path
$base_dir = __DIR__ . '/../';
$upload_dir = $base_dir . 'uploads/headers/';
$signature_dir = $base_dir . 'uploads/signatures/';

// Create directories if they don't exist
if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
if (!is_dir($signature_dir)) mkdir($signature_dir, 0755, true);

$allowed_types = ['image/png', 'image/jpeg', 'image/jpg', 'image/gif'];

// Fetch header settings (fallback)
$res = $conn->query("SELECT * FROM memo_header_settings WHERE id=1");
$header_settings = $res ? $res->fetch_assoc() : [];

// Use memo-specific header or fallback
$header_line1   = $memo['header_line1']   ?? ($header_settings['header_line1']   ?? 'Republic of the Philippines');
$header_line2   = $memo['header_line2']   ?? ($header_settings['header_line2']   ?? 'Region VII, Central Visayas');
$header_title   = $memo['header_title']   ?? ($header_settings['header_title']   ?? 'Municipality of Madridejos');
$header_school  = $memo['header_school']  ?? ($header_settings['header_school']  ?? 'MADRIDEJOS COMMUNITY COLLEGE');
$header_address = $memo['header_address'] ?? ($header_settings['header_address'] ?? 'Crossing Bunakan, Madridejos, Cebu');
$header_office  = $memo['header_office']  ?? ($header_settings['header_office']  ?? 'OFFICE OF THE COLLEGE PRESIDENT');
$header_logo    = $memo['header_logo']    ?? ($header_settings['header_logo']    ?? 'default_logo.png');
$header_seal    = $memo['header_seal']    ?? ($header_settings['header_seal']    ?? 'default_seal.png');

// Get logged-in user department
$user_id = $_SESSION['user_id'];
$user_department = "";
$stmt_user = $conn->prepare("SELECT department FROM users WHERE id=? LIMIT 1");
$stmt_user->bind_param("i", $user_id);
$stmt_user->execute();
$stmt_user->bind_result($user_department);
$stmt_user->fetch();
$stmt_user->close();

// Recipients
$recipients_result = $conn->query("SELECT department FROM memo_recipients WHERE memo_id = $memo_id");
$existing_recipients = [];
while ($row = $recipients_result->fetch_assoc()) {
    $existing_recipients[] = $row['department'];
}

// Department list
$departments = [
    "Office of the College President",
    "Office of the Registrar",
    "Office of the Student Affairs",
    "Faculty",
    "Finance Office",
    "Library",
    "BSIT Department",
    "BSBA Department",
    "BEED Department",
    "HM Department",
    "Guidance Office"
];

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $to = $_POST["to"] ?? [];
    $to = array_unique(array_filter(array_map('trim', $to)));
    $to_string = !empty($to) ? implode(", ", $to) : "All Departments";

    $from = $user_department;
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
            if ($data === false) {
                continue; // Invalid base64
            }

            $oldSignature = $signatories[$i]['signature'] ?? '';
            $imgPath = 'sig_' . uniqid() . '.png';
            $target = $signature_dir . $imgPath;

            if (!file_put_contents($target, $data)) {
                error_log("Failed to save signature: $target");
                continue;
            }

            // Remove old signature if exists and not default
            if ($oldSignature && $oldSignature !== $imgPath && file_exists($signature_dir . $oldSignature)) {
                unlink($signature_dir . $oldSignature);
            }
        } else {
            // Keep existing signature
            $imgPath = $signatories[$i]['signature'] ?? '';
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

    // Handle logo upload
    $logo_filename = $memo['header_logo'];
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] == 0) {
        if (!in_array($_FILES['logo']['type'], $allowed_types)) {
            die("Invalid logo file type. Only PNG, JPG, JPEG, GIF allowed.");
        }
        $ext = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
        $new_logo = 'logo_' . uniqid() . '.' . $ext;
        $target = $upload_dir . $new_logo;

        if (move_uploaded_file($_FILES['logo']['tmp_name'], $target)) {
            // Remove old logo if not default
            if ($logo_filename && $logo_filename !== 'default_logo.png' && file_exists($upload_dir . $logo_filename)) {
                unlink($upload_dir . $logo_filename);
            }
            $logo_filename = $new_logo;
        } else {
            die("Failed to save logo.");
        }
    }

    // Handle seal upload
    $seal_filename = $memo['header_seal'];
    if (isset($_FILES['seal']) && $_FILES['seal']['error'] == 0) {
        if (!in_array($_FILES['seal']['type'], $allowed_types)) {
            die("Invalid seal file type. Only PNG, JPG, JPEG, GIF allowed.");
        }
        $ext = pathinfo($_FILES['seal']['name'], PATHINFO_EXTENSION);
        $new_seal = 'seal_' . uniqid() . '.' . $ext;
        $target = $upload_dir . $new_seal;

        if (move_uploaded_file($_FILES['seal']['tmp_name'], $target)) {
            if ($seal_filename && $seal_filename !== 'default_seal.png' && file_exists($upload_dir . $seal_filename)) {
                unlink($upload_dir . $seal_filename);
            }
            $seal_filename = $new_seal;
        } else {
            die("Failed to save seal.");
        }
    }

    // Update memo
    $signature_json = json_encode($signatories, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    $first_signatory = $signatories[0] ?? ['name' => '', 'position' => '', 'org' => ''];

    // Update memo
$signature_json = json_encode($signatories, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
$first_signatory = $signatories[0] ?? ['name' => '', 'position' => '', 'org' => ''];

$stmt_update = $conn->prepare("
    UPDATE memos SET
        `from` = ?,
        `to` = ?,
        subject = ?,
        body = ?,
        header_line1 = ?,
        header_line2 = ?,
        header_title = ?,
        header_school = ?,
        header_address = ?,
        header_office = ?,
        header_logo = ?,
        header_seal = ?,
        signed_by = ?,
        sign_position = ?,
        sign_org = ?,
        signature_data = ?
    WHERE id = ?
");

$stmt_update->bind_param(
    "ssssssssssssssssi",  // ✅ 16 's' + 1 'i'
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
    $memo_id
);
    if (!$stmt_update->execute()) {
        die("Error updating memo: " . $stmt_update->error);
    }

    // Update recipients
    $conn->query("DELETE FROM memo_recipients WHERE memo_id = $memo_id");
    foreach ($to as $dept) {
        $dept = trim($dept);
        if (empty($dept)) continue;
        $stmt_rec = $conn->prepare("INSERT INTO memo_recipients (memo_id, department) VALUES (?, ?)");
        $stmt_rec->bind_param("is", $memo_id, $dept);
        $stmt_rec->execute();
        $stmt_rec->close();
    }

    // Log activity
    $action = "Updated memo";
    $details = "Subject: $subject";
    $log_stmt = $conn->prepare("INSERT INTO activity_log (user_id, action, memo_id, details) VALUES (?, ?, ?, ?)");
    $log_stmt->bind_param("isis", $user_id, $action, $memo_id, $details);
    $log_stmt->execute();
    $log_stmt->close();

    $memo_number_str = sprintf('%03d', $memo['memo_number']);
    header("Location: memos.php?msg=updated&memo_number=" . $memo_number_str);
    exit;
}

include "../includes/admin_sidebar.php";
?>
<!DOCTYPE html>
<html>
<head>
    <title>Edit Memorandum - Admin Panel</title>
    <link rel="stylesheet" href="../includes/user_style.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        body { background: #f5f5f5; font-family: Arial, sans-serif; color: #000; }
        .container { max-width: 800px; margin: 0 auto; padding: 20px; }
        h2 { color: #000; margin-bottom: 20px; }
        form { padding: 24px; border-radius: 8px; box-shadow: 0 1px 6px rgba(0,0,0,0.1); margin-bottom: 30px; }
        label { display: block; margin-top: 12px; font-weight: bold; color: #000; }
        input[type="text"], textarea { width: 100%; padding: 8px; margin-top: 6px; border: 1px solid #ccc; border-radius: 4px; font-size: 14px; color: #000; }
        textarea { min-height: 120px; resize: vertical; }
        .form-group { margin-top: 12px; }
        .btn { display: inline-block; padding: 10px 16px; margin-top: 20px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; }
        .btn:hover { background: #0056b3; }
        .btn.secondary { background: #6c757d; color: white; }
        .btn.secondary:hover { background: #5a6268; }

        /* Memo Preview */
        .memo-main {
            width: 760px; margin: 40px auto; background: white; padding: 30px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.07); font-family: 'Times New Roman', serif;
            line-height: 1.5; color: #000;
        }
        .header-container { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; gap: 20px; }
        .logo, .seal { width: 100px; height: 100px; object-fit: contain; }
        .center-header { flex: 1; text-align: center; margin: 0 20px; }
        .center-header div { margin: 2px 0; font-size: 12px; }
        .school-name { font-size: 14px; font-weight: bold; margin: 4px 0; }
        .header-line { border-top: 1px solid black; margin: 10px 0; }
        .office-name { text-align: center; font-weight: bold; font-size: 14px; margin: 15px 0; text-transform: uppercase; }
        .memo-number-date { font-size: 14px; margin: 15px 0; }
        .memo-meta { margin: 15px 0; font-size: 14px; }
        .memo-meta label { font-weight: bold; min-width: 40px; display: inline-block; }
        .memo-subject { font-weight: bold; margin: 15px 0; font-size: 14px; }
        .memo-body { margin: 15px 0; font-size: 14px; line-height: 1.6; white-space: pre-line; }
        .signature-block-left { margin-top: 50px; margin-left: 20px; font-size: 14px; line-height: 1.6; text-align: left; }
        .signature-container { display: flex; flex-direction: column; align-items: flex-start; gap: 4px; margin-top: 15px; }
        .signature-img { max-width: 200px; height: auto; border-bottom: 1px solid #000; margin-bottom: 2px; }
        .signatory-item { margin-bottom: 15px; padding: 15px; border: 1px solid #ddd; border-radius: 6px; position: relative; }
        .btn-remove-signatory { position: absolute; top: 8px; right: 8px; background: red; color: white; border: none; width: 24px; height: 24px; font-size: 14px; text-align: center; line-height: 1; border-radius: 50%; cursor: pointer; padding: 0; font-weight: bold; }
        @media (max-width: 800px) {
            .memo-main { width: 100%; padding: 15px; margin: 10px auto; }
            .header-container { flex-direction: column; }
            .logo, .seal { margin: 10px auto; }
        }
        @media print { .no-print, .btn, .form-group { display: none !important; } }
    </style>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.tiny.cloud/1/uxdub7o368aviboi5yyjizj1kgzcguypv7ud50dfv5m8unbd/tinymce/7/tinymce.min.js" referrerpolicy="origin"></script>
</head>
<body>
<div class="container">
    <h2>Edit Memorandum</h2>

    <form method="post" enctype="multipart/form-data" autocomplete="off" oninput="memoPreviewUpdate()">
        <label for="to">Recipients (Department/Organization):</label>
        <select id="to" name="to[]" multiple="multiple" style="width:100%;">
            <?php foreach ($departments as $dept): ?>
                <option value="<?= htmlspecialchars($dept) ?>"
                    <?= in_array($dept, $existing_recipients) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($dept) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label for="from">From:</label>
        <input type="text" id="from" value="<?= htmlspecialchars($user_department) ?>" readonly>

        <label for="subject">Subject:</label>
        <input type="text" id="subject" name="subject" value="<?= htmlspecialchars($memo['subject']) ?>" required>

        <label for="body"><strong>Body:</strong></label>
        <textarea id="body" name="body" required><?= htmlspecialchars($memo['body']) ?></textarea>

        <!-- File Uploads -->
        <label for="logo">Upload New Logo (optional):</label>
        <input type="file" id="logo" name="logo" accept="image/png, image/jpeg, image/jpg, image/gif">

        <label for="seal">Upload New Seal (optional):</label>
        <input type="file" id="seal" name="seal" accept="image/png, image/jpeg, image/jpg, image/gif">

        <!-- Signatories -->
        <div class="form-group">
            <label>Signatories:</label>
            <div id="signatories-container">
                <?php foreach ($signatories as $idx => $signer): ?>
                    <div class="signatory-item">
                        <button type="button" class="btn-remove-signatory">×</button>
                        <div class="form-group">
                            <label>Signed By:</label>
                            <input type="text" name="signed_by[]" class="signed-by-input" value="<?= htmlspecialchars($signer['name']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Position:</label>
                            <input type="text" name="sign_position[]" class="sign-position-input" value="<?= htmlspecialchars($signer['position']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Department/Organization:</label>
                            <input type="text" name="sign_org[]" class="sign-org-input" value="<?= htmlspecialchars($signer['org']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Digital Signature (Draw below):</label>
                            <canvas class="signature-canvas" width="300" height="100" style="border: 1px dotted #aaa;"></canvas>
                            <button type="button" class="clear-canvas">Clear</button>
                            <input type="hidden" name="signature_image[]" class="signature-data"
                                   value="<?= !empty($signer['signature']) ? htmlspecialchars($signer['signature']) : '' ?>">
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="button" id="add-signatory" class="btn" style="margin-top: 10px;">+ Add Another Signatory</button>
        </div>

        <button type="submit" class="btn">Update Memorandum</button>
        <a href="memos.php" class="btn secondary">Cancel</a>
    </form>

    <!-- MEMO PREVIEW -->
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
            NO. <?= date('Y') ?> – <span id="memoNumberPreview"><?= sprintf('%03d', $memo['memo_number']) ?></span><br>
            <span id="datePreview"><?= date('F j, Y') ?></span>
        </div>

        <div class="memo-meta">
            <div><label>TO:</label> <span id="toPreview"><?= htmlspecialchars(implode(", ", $existing_recipients)) ?></span></div>
            <div><label>FROM:</label> <?= htmlspecialchars($user_department) ?></div>
        </div>

        <div class="memo-subject">
            <label>SUBJECT:</label>
            <span id="subjectPreview" style="display: inline-block; margin-left: 4px;"><?= htmlspecialchars($memo['subject']) ?></span>
        </div>

        <div class="memo-body" id="bodyPreview"><?= nl2br(htmlspecialchars($memo['body'])) ?></div>

        <div class="signature-block-left">
            <div>Signed by:</div>
            <div id="signaturesPreview"></div>
        </div>
    </div>
</div>

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

    canvas.addEventListener('mousedown', startDrawing);
    canvas.addEventListener('mousemove', draw);
    canvas.addEventListener('mouseup', stopDrawing);
    canvas.addEventListener('mouseout', stopDrawing);
    canvas.addEventListener('touchstart', handleTouch, { passive: false });
    canvas.addEventListener('touchmove', handleTouch, { passive: false });
    canvas.addEventListener('touchend', stopDrawing);

    const clearBtn = canvas.closest('.signatory-item')?.querySelector('.clear-canvas');
    if (clearBtn) clearBtn.onclick = clearCanvas;

    return { clearCanvas, updateHiddenField };
}
</script>

<script>
// Add new signatory
document.getElementById('add-signatory').addEventListener('click', function () {
    const container = document.querySelectorAll('.signatory-item').length;
    if (container >= 10) {
        alert("Maximum 10 signatories allowed.");
        return;
    }

    const containerEl = document.getElementById('signatories-container');
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
// Real-time preview update
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

            if (imgData && imgData.startsWith('data:image')) {
                const img = document.createElement('img');
                img.src = imgData;
                img.className = 'signature-img';
                container.appendChild(img);
            } else if (imgData && imgData.startsWith('sig_')) {
                const img = document.createElement('img');
                img.src = '../uploads/signatures/' + imgData;
                img.className = 'signature-img';
                img.onerror = () => {
                    console.warn("Signature image not found:", img.src);
                };
                container.appendChild(img);
            }

            const text = document.createElement('div');
            text.innerHTML = `<strong>${name}</strong><br>${position || '[Position]'}<br>${org || '[Department]'}`;
            container.appendChild(text);
            preview.appendChild(container);
        });

        // Logo preview
        const logoInput = document.getElementById('logo');
        if (logoInput && logoInput.files && logoInput.files[0]) {
            const reader = new FileReader();
            reader.onload = e => {
                const logoImg = document.querySelector('.logo');
                if (logoImg) logoImg.src = e.target.result;
            };
            reader.readAsDataURL(logoInput.files[0]);
        }

        // Seal preview
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
        console.error("Preview error:", e);
    }
}
</script>

<script>
// Initialize on load
window.onload = function () {
    document.querySelectorAll('.signature-canvas').forEach((canvas, index) => {
        initCanvas(canvas);

        const item = canvas.closest('.signatory-item');
        const hiddenInput = item.querySelector('.signature-data');
        const signatureFile = hiddenInput.value;

        if (signatureFile && signatureFile.startsWith('sig_')) {
            const imgPath = '../uploads/signatures/' + signatureFile;
            const img = new Image();
            img.onload = function () {
                const ctx = canvas.getContext('2d');
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
            };
            img.onerror = function () {
                console.warn("Failed to load signature image:", imgPath);
            };
            img.src = imgPath;
        }
    });

    $('#to').select2({
        placeholder: "Select departments...",
        tags: true,
        allowClear: true,
        width: '100%'
    });

    $('#to').on('change', memoPreviewUpdate);
    memoPreviewUpdate();
};

// TinyMCE
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