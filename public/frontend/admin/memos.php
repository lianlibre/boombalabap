<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once 'auth_admin.php';

// Must be logged in
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    die("Not logged in.");
}

// Get user's role from database
$stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user_data = $result->fetch_assoc();
$stmt->close();

if (!$user_data || empty($user_data['role'])) {
    die("Your account has no role assigned.");
}

$user_role = trim($user_data['role']);

// 🔹 Role mapping: map user.role → memo.to values
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
if (empty($allowed_recipients)) {
    $allowed_recipients = ['__NEVER_MATCH__'];
}

// Escape for SQL
$escaped_recipients = array_map(fn($r) => $conn->real_escape_string($r), $allowed_recipients);

// Build recipient conditions
$conditions = [];
foreach ($escaped_recipients as $r) {
    $conditions[] = "m.`to` = '$r'";
    $conditions[] = "m.`to` LIKE '$r,%'";
    $conditions[] = "m.`to` LIKE '%,$r'";
    $conditions[] = "m.`to` LIKE '%,$r,%'";
}
$recipient_clause = implode(' OR ', $conditions);

// Allow broadcast lists
$recipient_clause .= " OR m.`to` IN ('All Personnel', 'All', 'All Departments')";

// ✅ NEW: Always show memos sent by this user
$where_clause = "($recipient_clause) OR m.user_id = $user_id";

// Archive logic (only sender can archive)
if (isset($_GET['archive']) && is_numeric($_GET['archive'])) {
    $archive_id = intval($_GET['archive']);

    $check = $conn->query("SELECT user_id FROM memos WHERE id = $archive_id");
    $memo = $check->fetch_assoc();

    if ($memo && $memo['user_id'] == $user_id) {
        $conn->query("UPDATE memos SET archived = 1 WHERE id = $archive_id");
        header("Location: memos.php?msg=archived");
        exit;
    } else {
        die("You can only archive memos you created.");
    }
}

// Pagination
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Count total visible memos: recipient OR sender
$total_sql = "SELECT COUNT(*) as total FROM memos m WHERE m.archived = 0 AND ($where_clause)";
$total_res = $conn->query($total_sql);
$total = $total_res->fetch_assoc()['total'];

// Get memos: recipient OR sender
$sql = "
    SELECT m.*, u.department as sender_dept
    FROM memos m
    JOIN users u ON m.user_id = u.id
    WHERE m.archived = 0 AND ($where_clause)
    ORDER BY m.created_at DESC
    LIMIT $per_page OFFSET $offset
";

$memos = $conn->query($sql);

include "../includes/admin_sidebar.php";
?>

<!DOCTYPE html>
<html>
<head>
    <title>My Memorandums - Admin Panel</title>
    <link rel="stylesheet" href="../includes/user_style.css">

    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css" rel="stylesheet" />

    <!-- Font Awesome for Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet" />

    <style>
        .container {
            max-width: 1200px;
            margin: auto;
            padding: 20px;
        }

        .btn {
            display: inline-block;
            padding: 8px 12px;
            margin: 10px 5px;
            background-color: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 4px;
        }

        .btn:hover {
            background-color: #0056b3;
        }

        .actions-cell {
            display: flex;
            gap: 8px;
            align-items: center;
            justify-content: center;
        }

        .action-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 24px;
            height: 24px;
            text-decoration: none;
            transition: transform 0.2s ease;
            border-radius: 4px;
            padding: 2px;
        }

        .action-icon:hover {
            transform: scale(1.1);
        }

        .action-icon i {
            font-size: 16px;
        }

        .disabled {
            opacity: 0.4;
            cursor: not-allowed;
        }
    </style>
</head>
<body>
<div class="container">
    <h2>My Memorandums</h2>

    <a href="archived_memos.php" class="btn"><i class="fas fa-archive"></i> Archived</a>
    <a href="memo_add.php" class="btn"><i class="fas fa-plus"></i> Create New</a>

    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'archived'): ?>
        <div style="color: #6492fcff; margin: 10px 0;">
            <b><i class="fas fa-check-circle"></i> Memorandum archived!</b>
        </div>
    <?php endif; ?>

    <?php if ($memos->num_rows == 0): ?>
        <p style="color: #666; text-align: center; margin-top: 30px;">
            <i>You have no memorandums sent or addressed to your role: <strong><?= htmlspecialchars(ucfirst($user_role)) ?></strong>.</i>
        </p>
    <?php else: ?>
        <table id="memosTable" class="display" style="width:100%">
            <thead>
                <tr>
                    <th>No.</th>
                    <th>Subject</th>
                    <th>Body</th>
                    <th>To</th>
                    <th>From (Dept)</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $row_num = $offset + 1;
                while ($memo = $memos->fetch_assoc()):
                    $is_sender = ($memo['user_id'] == $user_id);
                ?>
                <tr>
                    <td><?= $row_num ?></td>
                    <td><?= htmlspecialchars($memo['subject']) ?></td>
                    <td><?= htmlspecialchars(mb_strimwidth($memo['body'], 0, 70, "...")) ?></td>
                    <td><?= htmlspecialchars($memo['to'] ?: '-') ?></td>
                    <td><?= htmlspecialchars($memo['from']) ?></td>
                    <td><?= htmlspecialchars($memo['created_at']) ?></td>
                    <td class="actions-cell">
                        <!-- Edit: Only if sender -->
                        <?php if ($is_sender): ?>
                            <a href="memo_edit.php?id=<?= $memo['id'] ?>" title="Edit" class="action-icon">
                                <i class="fas fa-edit" style="color: #007bff;"></i>
                            </a>
                        <?php else: ?>
                            <span class="action-icon disabled" title="Only the sender can edit">
                                <i class="fas fa-edit" style="color: #ccc;"></i>
                            </span>
                        <?php endif; ?>

                        <!-- View: Always allowed if visible -->
                        <a href="memo_message.php?id=<?= $memo['id'] ?>" title="View Message" class="action-icon">
                            <i class="fas fa-envelope" style="color: #28a745;"></i>
                        </a>

                        <!-- Archive: Only if sender -->
                        <?php if ($is_sender): ?>
                            <a href="memos.php?archive=<?= $memo['id'] ?>" class="action-icon archive-link"
                               onclick="return confirm('Archive this memorandum?')" title="Archive">
                                <i class="fas fa-box-archive" style="color: #dc3545;"></i>
                            </a>
                        <?php else: ?>
                            <span class="action-icon disabled" title="Only the sender can archive">
                                <i class="fas fa-box-archive" style="color: #ccc;"></i>
                            </span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php
                $row_num++;
                endwhile;
                ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php include "../includes/admin_footer.php"; ?>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script>
$(document).ready(function() {
    $('#memosTable').DataTable({
        "pageLength": 10,
        "order": [[5, "desc"]],
        "columnDefs": [
            { "orderable": false, "targets": 6 },
            { "searchable": false, "targets": 0 }
        ],
        "language": {
            "emptyTable": "No memorandums to show."
        }
    });
});
</script>
</body>
</html>