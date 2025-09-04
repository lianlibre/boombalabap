<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "../includes/permissions.php";

// Must be logged in
if (!isset($_SESSION['user_id'])) {
    die("You are not logged in.");
}
$user_id = $_SESSION['user_id'];

// Validate ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid memo ID.");
}
$id = intval($_GET['id']);

// Fetch memo with sender info
$query = "
    SELECT 
        m.id,
        m.user_id AS sender_id,
        m.memo_number,
        m.subject,
        m.body,
        m.to,
        m.`from`,
        m.created_at,
        m.signed_by,
        m.sign_position,
        m.sign_org,
        m.signature_data,
        m.header_line1,
        m.header_line2,
        m.header_title,
        m.header_school,
        m.header_address,
        m.header_office,
        m.header_logo,
        m.header_seal,
        u.fullname AS sender_name,
        u.role AS sender_role
    FROM memos m
    LEFT JOIN users u ON m.user_id = u.id
    WHERE m.id = ?
";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    die("Memo not found.");
}

$memo = $result->fetch_assoc();
$stmt->close();

// 🔐 Check: Is user the sender?
$is_sender = ($memo['sender_id'] == $user_id);

// 🔐 Check: Is user a recipient?
$role_stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
$role_stmt->bind_param("i", $user_id);
$role_stmt->execute();
$role_result = $role_stmt->get_result();
$user_data = $role_result->fetch_assoc();
$role_stmt->close();

if (!$user_data) {
    die("User not found.");
}
$user_role = trim($user_data['role']);

// 🔹 Role mapping for recipients
$role_mapping = [
    'admin' => ['Admin', 'ADMIN', 'OFFICE OF THE PRES', 'Office of the College President'],
    'student' => ['Students', 'STUDENTS', 'STUDENT'],
    'school_counselor' => ['School Counselor', 'SCHOOL COUNSELOR', 'school counsel', 'Guidance Office'],
    'library' => ['Library', 'LIBRARY'],
    'soa' => ['Office of SOA', 'OFFICE OF SOA', 'SOA', 'Office of the Student Affairs'],
    'guidance' => ['Guidance', 'GUIDANCE', 'Guidance Office'],
    'dept_head_bsit' => ['BSIT Department Head', 'DEPT HEAD BSIT', 'BSIT Department'],
    'dept_head_bsba' => ['BSBA Department Head', 'DEPT HEAD BSBA', 'BSBA Department'],
    'dept_head_bshm' => ['BSHM Department Head', 'DEPT HEAD BSHM', 'HM Department'],
    'dept_head_beed' => ['BEED Department Head', 'DEPT HEAD BEED', 'BEED Department'],
    'instructor' => ['Instructors', 'INSTRUCTORS', 'Instructor', 'Faculty'],
    'non_teaching' => ['Non Teaching Personnel', 'NON TEACHING PERSONNEL\'S', 'UTILITY', 'GUARD', 'Non-Teaching Staff']
];

$allowed_recipients = $role_mapping[$user_role] ?? [];
$escaped_to = $conn->real_escape_string($memo['to']);

// Build recipient check
$recipient_conditions = [];
foreach ($allowed_recipients as $r) {
    $r = $conn->real_escape_string($r);
    $recipient_conditions[] = "('$escaped_to' = '$r')";
    $recipient_conditions[] = "('$escaped_to' LIKE '$r,%')";
    $recipient_conditions[] = "('$escaped_to' LIKE '%,$r')";
    $recipient_conditions[] = "('$escaped_to' LIKE '%,$r,%')";
}
$recipient_conditions[] = "('$escaped_to' IN ('All Personnel', 'All', 'All Departments'))";

$recipient_match = false;
foreach ($recipient_conditions as $cond) {
    if ($conn->query("SELECT $cond")->fetch_row()[0]) {
        $recipient_match = true;
        break;
    }
}

// 🔐 Final Access: User must be sender OR recipient
if (!$is_sender && !$recipient_match) {
    die("You are not authorized to view this memo.");
}

// 🔹 Role-to-prefix mapping (same as in memo_add.php)
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

// Use sender's role to determine prefix
$sender_role = $memo['sender_role'];
$prefix = $prefix_mapping[$sender_role] ?? 'NO'; // fallback to 'NO'

// Format memo number
$formatted_memo_number = sprintf('%03d', $memo['memo_number']);
$full_memo_number = "$prefix. $formatted_memo_number Series of " . date('Y');

// Parse signature_data
$signatories = [];
if (!empty($memo['signature_data'])) {
    $parsed = json_decode($memo['signature_data'], true);
    if (is_array($parsed)) {
        $signatories = $parsed;
    }
}

// Fallback to single signatory
if (empty($signatories) && !empty($memo['signed_by'])) {
    $signatories = [
        [
            'name' => $memo['signed_by'],
            'position' => $memo['sign_position'] ?? '',
            'org' => $memo['sign_org'] ?? '',
            'signature' => '',
        ]
    ];
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>View Memorandum</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

    <style>
        body {
            font-family: 'Times New Roman', serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 20px;
            color: #000;
        }

        .controls {
            max-width: 800px;
            margin: 0 auto 20px;
            display: flex;
            gap: 10px;
            align-items: center;
            padding: 10px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .controls select, .controls button {
            padding: 8px 12px;
            font-size: 14px;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            position: relative;
            padding: 20px;
        }

        /* Paper Size Classes */
        .paper-a4 { width: 21cm; min-height: 29.7cm; padding: 2cm; margin: 0 auto; }
        .paper-letter { width: 8.5in; min-height: 11in; padding: 1in; margin: 0 auto; }
        .paper-legal { width: 8.5in; min-height: 14in; padding: 1in; margin: 0 auto; }

        @media print {
            .controls, .actions {
                display: none !important;
            }
            body { background: white; margin: 0; padding: 0; }
            .container { box-shadow: none; margin: 0; width: 100%; overflow: visible; }
            @page a4 { size: A4 portrait; margin: 0; }
            @page letter { size: letter portrait; margin: 0; }
            @page legal { size: legal portrait; margin: 0; }
            .paper-a4 { page: a4; }
            .paper-letter { page: letter; }
            .paper-legal { page: legal; }
            img { max-width: 100%; height: auto; }
            * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }

        /* Header Section */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #000;
        }

        .logo {
            width: 100px;
            height: auto;
            object-fit: contain;
        }

        .center-text {
            flex: 1;
            text-align: center;
            font-size: 12px;
            line-height: 1.3;
            margin: 0;
        }

        .header-title {
            font-weight: bold;
            font-size: 14px;
            margin-bottom: 4px;
        }

        .header-school {
            font-weight: bold;
            margin-bottom: 4px;
        }

        .header-address {
            margin-bottom: 4px;
        }

        .header-office {
            font-style: italic;
            font-size: 12px;
        }

        .office-name {
            text-align: center;
            font-weight: bold;
            font-size: 16px;
            margin: 10px 0;
            text-transform: uppercase;
        }

        /* Memo Content */
        .content {
            margin-top: 30px;
            line-height: 1.6;
            font-size: 14px;
        }

        .body-content h1 {
            font-size: 24px;
            margin: 20px 0 10px;
            text-align: center;
        }

        .body-content h2 {
            font-size: 20px;
            margin: 18px 0 10px;
            border-bottom: 1px solid #ccc;
            padding-bottom: 2px;
        }

        .body-content ul, .body-content ol {
            margin: 10px 0;
            padding-left: 30px;
        }

        .body-content li {
            margin: 4px 0;
        }

        .body-content p {
            margin: 10px 0;
            line-height: 1.8;
        }

        .body-content strong, .body-content b {
            font-weight: bold;
        }

        .body-content em, .body-content i {
            font-style: italic;
        }

        .body-content u {
            text-decoration: underline;
        }

        /* Signature Section */
        .signature {
            margin-top: 50px;
            padding-top: 20px;
        /*  border-top: 1px dashed #ccc;*/
        }

        .signature-container {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            margin-bottom: 20px;
        }

        .signature-img {
            max-width: 200px;
            height: auto;
            border-bottom: 1px solid #000;
            margin-bottom: 4px;
        }

        .signature-name {
            font-weight: bold;
            margin: 0;
        }

        .signature-position {
            font-size: 14px;
            margin: 2px 0;
        }

        /* Actions */
        .actions {
            margin-top: 30px;
            display: flex;
            gap: 10px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            text-decoration: none;
        }

        .btn-back { background-color: #6c757d; color: white; }
        .btn-edit { background-color: #007bff; color: white; }
        .btn-pdf, .btn-print { background-color: #28a745; color: white; }
        .btn:hover { opacity: 0.9; }
    </style>
</head>
<body>

<div class="controls">
    <label for="paperSize">Paper Size:</label>
    <select id="paperSize" onchange="changePaperSize(this.value)">
        <option value="a4">A4</option>
        <option value="letter">Letter</option>
        <option value="legal">Legal</option>
    </select>

    <button onclick="printDocument()" class="btn btn-print">
        <i class="fas fa-print"></i> Print
    </button>

    <button onclick="saveAsPDF()" class="btn btn-pdf">
        <i class="fas fa-file-pdf"></i> Save as PDF
    </button>
</div>

<div class="container paper-a4" id="memoContainer">

    <!-- Header with Logos and Text -->
    <div class="header">
        <?php if ($memo['header_logo']): ?>
            <img src="../uploads/headers/<?= htmlspecialchars($memo['header_logo']) ?>" alt="Logo" class="logo" />
        <?php endif; ?>

        <div class="center-text">
            <?php if ($memo['header_line1']): ?>
                <div><?= htmlspecialchars($memo['header_line1']) ?></div>
            <?php endif; ?>
            <?php if ($memo['header_line2']): ?>
                <div><?= htmlspecialchars($memo['header_line2']) ?></div>
            <?php endif; ?>
            <?php if ($memo['header_title']): ?>
                <div class="header-title"><?= htmlspecialchars($memo['header_title']) ?></div>
            <?php endif; ?>
            <?php if ($memo['header_school']): ?>
                <div class="header-school"><?= htmlspecialchars($memo['header_school']) ?></div>
            <?php endif; ?>
            <?php if ($memo['header_address']): ?>
                <div class="header-address"><?= htmlspecialchars($memo['header_address']) ?></div>
            <?php endif; ?>
        </div>

        <?php if ($memo['header_seal']): ?>
            <img src="../uploads/headers/<?= htmlspecialchars($memo['header_seal']) ?>" alt="Seal" class="logo" />
        <?php endif; ?>
    </div>

    <!-- Office Name -->
    <div class="office-name">
        <?= htmlspecialchars($memo['header_office'] ?? '') ?>
    </div>

    <!-- Memo Content -->
    <div class="content">
        <p><strong>MEMORANDUM ORDER</strong></p>
        <p><strong><?= htmlspecialchars($full_memo_number) ?></strong></p>
        <p><?= htmlspecialchars(date('F j, Y', strtotime($memo['created_at']))) ?></p>

        <p><strong>TO:</strong> <?= htmlspecialchars($memo['to'] ?? '') ?></p>
        <p><strong>FROM:</strong> <?= htmlspecialchars($memo['from'] ?? '') ?></p>
        <p><strong>SUBJECT:</strong> <?= htmlspecialchars($memo['subject'] ?? '') ?></p>

        <!-- Render HTML safely -->
        <div class="body-content">
            <?= htmlspecialchars_decode(
                strip_tags($memo['body'], '<b><i><u><strong><em><p><br><ol><ul><li><h1><h2><h3><h4><h5><h6>')
            ) ?>
        </div>

        <!-- Signatures -->
        <div class="signature">
            <p><strong>Signed by:</strong></p>
            <?php foreach ($signatories as $signer): ?>
                <div class="signature-container">
                    <!-- Signature Image -->
                    <?php if (!empty($signer['signature'])): 
                        $filename = basename($signer['signature']);
                        $filepath = "../uploads/signatures/" . $filename;
                    ?>
                        <img 
                            src="<?= htmlspecialchars($filepath) ?>" 
                            alt="Signature" 
                            class="signature-img" 
                            onerror="this.style.display='none'; this.parentNode.querySelector('.sig-missing').style.display='block';"
                        />
                        <p class="sig-missing" style="display:none; color: #888; font-style: italic;">Signature not available</p>
                    <?php else: ?>
                        <p style="color: #888; font-style: italic;">Signature not available</p>
                    <?php endif; ?>

                    <!-- Name, Position, Org -->
                    <p class="signature-name"><?= htmlspecialchars($signer['name']) ?></p>
                    <p class="signature-position"><?= htmlspecialchars($signer['position']) ?></p>
                    <p class="signature-position"><?= htmlspecialchars($signer['org']) ?></p>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Actions -->
        <div class="actions">
            <a href="javascript:history.back()" class="btn btn-back">
                <i class="fas fa-arrow-left"></i> Back
            </a>

            <!-- Edit Button: Only show if user is sender -->
            <?php if ($is_sender): ?>
                <a href="memo_edit.php?id=<?= $memo['id'] ?>" class="btn btn-edit">
                    <i class="fas fa-edit"></i> Edit
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    function changePaperSize(size) {
        document.getElementById('memoContainer').className = `container paper-${size}`;
    }

    function printDocument() {
        const controls = document.querySelector('.controls');
        const actions = document.querySelector('.actions');
        controls.style.display = 'none';
        actions.style.display = 'none';

        setTimeout(() => window.print(), 100);

        window.addEventListener('afterprint', () => {
            controls.style.display = 'flex';
            actions.style.display = 'flex';
        });
    }

    function saveAsPDF() {
        const { jsPDF } = window.jspdf;
        const element = document.getElementById('memoContainer');
        const controls = document.querySelector('.controls');
        const actions = document.querySelector('.actions');

        controls.style.display = 'none';
        actions.style.display = 'none';

        setTimeout(() => {
            html2canvas(element, {
                scale: 2,
                useCORS: true,
                logging: false,
                backgroundColor: '#ffffff'
            }).then(canvas => {
                const imgData = canvas.toDataURL('image/jpeg', 0.95);
                const pdf = new jsPDF({
                    orientation: 'portrait',
                    unit: 'px',
                    format: 'a4'
                });

                const width = pdf.internal.pageSize.getWidth();
                const height = (canvas.height * width) / canvas.width;

                pdf.addImage(imgData, 'JPEG', 0, 0, width, height);
                pdf.save(`memo_<?= $memo['memo_number'] ?>.pdf`);

                // Restore UI
                controls.style.display = 'flex';
                actions.style.display = 'flex';
            }).catch(err => {
                console.error("PDF generation error:", err);
                alert("Failed to generate PDF.");
                controls.style.display = 'flex';
                actions.style.display = 'flex';
            });
        }, 100);
    }
</script>

</body>
</html>