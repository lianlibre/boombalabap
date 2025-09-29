<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once 'auth_admin.php';

// Must be logged in
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    die("Not logged in.");
}

// Get user's role
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

// 🔹 Role-to-recipient mapping
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

// Escape recipients for SQL LIKE matching
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

// Only sender can see their own memos
$where_clause = "($recipient_clause) OR m.user_id = $user_id";

// ✅ Handle Archive via POST
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["archive_memo"])) {
    $memo_id = intval($_POST["memo_id"]);
    $check = $conn->query("SELECT user_id FROM memos WHERE id = $memo_id");
    if ($check->num_rows > 0) {
        $row = $check->fetch_assoc();
        if ($row['user_id'] == $user_id) {
            $conn->query("UPDATE memos SET archived = 1 WHERE id = $memo_id");
            $_SESSION['msg'] = "archived";
        } else {
            $_SESSION['msg'] = "unauthorized";
        }
    } else {
        $_SESSION['msg'] = "not_found";
    }
    header("Location: memos.php");
    exit;
}

// ✅ Fetch all visible memos + memo_number
$sql = "
    SELECT 
        m.memo_number,
        m.id,
        m.user_id, 
        m.subject,
        m.body,
        m.to,
        m.from,
        m.created_at,
        u.department as sender_dept
    FROM memos m
    JOIN users u ON m.user_id = u.id
    WHERE m.archived = 0 AND ($where_clause)
    ORDER BY m.created_at DESC
";

$memos = $conn->query($sql);

// Get session message
$msg = '';
if (isset($_SESSION['msg'])) {
    $msg = $_SESSION['msg'];
    unset($_SESSION['msg']);
}

include "../includes/admin_sidebar.php";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>My Memorandums</title>

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500&display=swap" rel="stylesheet">

    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css" />

    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />

    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" />

    <style>
        body {
            font-family: 'Roboto', Arial, sans-serif;
            background: #f4f6f9;
            margin: 0;
            padding: 0;
            color: #333;
        }

        .container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
        }

        h2 {
            text-align: center;
            color: #2c3e50;
            margin-bottom: 20px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 16px;
            margin: 0 8px 10px 0;
            background-color: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-size: 14px;
            transition: background 0.3s ease;
        }

        .btn:hover {
            background-color: #0056b3;
        }

        .btn-secondary {
            background-color: #6c757d;
        }

        .btn-secondary:hover {
            background-color: #565e64;
        }

        .btn-danger {
            background-color: #dc3545;
        }

        .btn-danger:hover {
            background-color: #c0392b;
        }

        /* Table Styling */
        table.dataTable {
            border-collapse: collapse;
            width: 100%;
            margin-top: 20px;
            background: #fff;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border-radius: 8px;
            overflow: hidden;
        }

        table.dataTable th,
        table.dataTable td {
            padding: 12px 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        table.dataTable th {
            background-color: #ecf0f1;
            color: #2c3e50;
            font-weight: 500;
        }

        table.dataTable tr:hover {
            background-color: #f8f9fa;
        }

        /* Actions Cell */
        .actions-cell {
            display: flex;
            justify-content: center;
            gap: 10px;
            white-space: nowrap;
        }

        .action-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: #f8f9fa;
            color: #495057;
            font-size: 14px;
            transition: all 0.2s ease;
            text-decoration: none;
        }

        .action-icon:hover {
            transform: scale(1.1);
            box-shadow: 0 0 5px rgba(0,0,0,0.2);
        }

        .action-icon.edit { color: #007bff; }
        .action-icon.view { color: #28a745; }
        .action-icon.archive { color: #dc3545; }

        .disabled {
            opacity: 0.4;
            pointer-events: none;
            cursor: not-allowed;
        }

        .no-data {
            text-align: center;
            color: #666;
            font-style: italic;
            margin: 30px 0;
            padding: 20px;
            background: #fdfdfd;
            border-radius: 8px;
            border: 1px dashed #ccc;
        }

        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }

            .btn span {
                display: none;
            }

            .btn i {
                font-size: 1.2rem;
            }

            .actions-cell {
                justify-content: flex-start;
            }

            .action-icon {
                width: 32px;
                height: 32px;
                font-size: 16px;
            }

            /* Responsive table scroll */
            .table-responsive {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
        }
    </style>
</head>
<body>
<div class="container">
    <h2>📬 My Memorandums</h2>

    <a href="memo_add.php" class="btn">
        <i class="fas fa-plus"></i> <span>Create New</span>
    </a>
    <a href="archived_memos.php" class="btn btn-secondary">
        <i class="fas fa-archive"></i> <span>Archived</span>
    </a>

    <?php if ($memos->num_rows == 0): ?>
        <div class="no-data">
            <i>You have no memorandums sent or addressed to your role: <strong><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $user_role))) ?></strong>.</i>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table id="memosTable" class="display">
                <thead>
                    <tr>
                        <th>Memo No.</th>
                        <th>Subject</th>
                        <th>Body Preview</th>
                        <th>To</th>
                        <th>From (Dept)</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($memo = $memos->fetch_assoc()): 
                        $is_sender = ($memo['user_id'] == $user_id);
                    ?>
                    <tr>
                        <!-- ✅ Show actual memo_number from DB -->
                        <td><?= htmlspecialchars($memo['memo_number']) ?></td>

                        <td><?= htmlspecialchars($memo['subject']) ?></td>
                        <td><?= htmlspecialchars(mb_strimwidth(strip_tags($memo['body']), 0, 80, "...")) ?></td>
                        <td><?= htmlspecialchars($memo['to'] ?: '-') ?></td>
                        <td><?= htmlspecialchars($memo['from']) ?> (<?= htmlspecialchars($memo['sender_dept'] ?? 'N/A') ?>)</td>
                        <td><?= date('M d, Y H:i', strtotime($memo['created_at'])) ?></td>
                        <td class="actions-cell">
                            <!-- Edit -->
                            <?php if ($is_sender): ?>
                                <a href="memo_edit.php?id=<?= $memo['id'] ?>" class="action-icon edit" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                            <?php else: ?>
                                <span class="action-icon disabled" title="Only sender can edit">
                                    <i class="fas fa-edit"></i>
                                </span>
                            <?php endif; ?>

                            <!-- View -->
                            <a href="memo_message.php?id=<?= $memo['id'] ?>" class="action-icon view" title="View Message">
                                <i class="fas fa-envelope-open"></i>
                            </a>

                            <!-- Archive -->
                            <?php if ($is_sender): ?>
                                <button type="button" class="action-icon archive archive-btn"
                                        data-id="<?= $memo['id'] ?>"
                                        title="Archive Memo">
                                    <i class="fas fa-box-archive"></i>
                                </button>
                            <?php else: ?>
                                <span class="action-icon disabled" title="Only sender can archive">
                                    <i class="fas fa-box-archive"></i>
                                </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<!-- DataTables JS -->
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>

<!-- SweetAlert2 JS -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
$(document).ready(function () {
    // Show SweetAlert2 message from session
    <?php if ($msg === 'archived'): ?>
        Swal.fire({ icon: 'success', title: 'Archived!', text: 'Memorandum moved to archive.' });
    <?php elseif ($msg === 'unauthorized'): ?>
        Swal.fire({ icon: 'error', title: 'Access Denied', text: 'You can only archive your own memos.' });
    <?php elseif ($msg === 'not_found'): ?>
        Swal.fire({ icon: 'error', title: 'Not Found', text: 'The memorandum does not exist.' });
    <?php endif; ?>

    // Initialize DataTable
    $('#memosTable').DataTable({
        "order": [[5, "desc"]],  // Sort by Date descending
        "columnDefs": [
            { "orderable": false, "targets": [6] },     // Disable sorting on Actions
            { "searchable": false, "targets": [0, 6] }  // Disable search on Memo No. & Actions
        ],
        "language": {
            "emptyTable": "No memorandums to show.",
            "search": "Search:",
            "lengthMenu": "Show _MENU_ entries",
            "info": "Showing _START_ to _END_ of _TOTAL_ memos"
        },
        "responsive": true,
        "autoWidth": false
    });

    // ✅ Handle Archive Button Click using SweetAlert2 + POST
    $('#memosTable tbody').on('click', '.archive-btn', function () {
        const memoId = $(this).data('id');

        Swal.fire({
            title: 'Archive Memorandum?',
            text: "This will move the memo to your archive. You can restore it later.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, archive it!',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                const form = $('<form>')
                    .attr('method', 'POST')
                    .append(
                        $('<input>').attr('name', 'memo_id').val(memoId),
                        $('<input>').attr('name', 'archive_memo').val('1')
                    )
                    .hide()
                    .appendTo('body')
                    .submit();
            }
        });
    });
});
</script>

<?php include "../includes/admin_footer.php"; ?>
</body>
</html>