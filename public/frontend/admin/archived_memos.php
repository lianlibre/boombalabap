<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";

// 🔐 Must be logged in
if (!isset($_SESSION['user_id'])) {
    die("You are not logged in.");
}

$user_id = $_SESSION['user_id'];

// ✅ Handle Retrieve via POST (secure)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["retrieve_memo"])) {
    $memo_id = intval($_POST["memo_id"]);

    // Optional: verify ownership? Only admin or sender can retrieve?
    $stmt = $conn->prepare("UPDATE memos SET archived = 0 WHERE id = ?");
    $stmt->bind_param("i", $memo_id);
    $stmt->execute();
    $stmt->close();

    $_SESSION['msg'] = "retrieved";
    header("Location: archived_memos.php");
    exit;
}

// Fetch all archived memos
$sql = "
    SELECT m.memo_number, m.id, m.subject, m.body, m.from, m.created_at, u.fullname 
    FROM memos m 
    JOIN users u ON m.user_id = u.id 
    WHERE m.archived = 1 
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
    <title>Archived Memorandums</title>

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
            margin-bottom: 15px;
            background-color: #6c757d;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-size: 14px;
            transition: background 0.3s ease;
        }

        .btn:hover {
            background-color: #565e64;
        }

        table.dataTable {
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

        .actions-cell {
            display: flex;
            justify-content: center;
            gap: 10px;
        }

        .action-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: #f8f9fa;
            color: #28a745;
            font-size: 14px;
            transition: all 0.2s ease;
            text-decoration: none;
        }

        .action-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 0 5px rgba(0,0,0,0.2);
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

            .action-btn {
                width: 32px;
                height: 32px;
                font-size: 16px;
            }
        }
    </style>
</head>
<body>
<div class="container">
    <h2>📦 Archived Memorandums</h2>

    <a href="memos.php" class="btn">
        <i class="fas fa-arrow-left"></i> <span>Back to Memos</span>
    </a>

    <?php if ($memos->num_rows == 0): ?>
        <div class="no-data">
            <i>No archived memorandums found.</i>
        </div>
    <?php else: ?>
        <table id="archivedMemosTable" class="display">
            <thead>
                <tr>
                    <th>Memo No.</th>
                    <th>Subject</th>
                    <th>Body Preview</th>
                    <th>From</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($memo = $memos->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($memo['memo_number']) ?></td>
                    <td><?= htmlspecialchars($memo['subject']) ?></td>
                    <td><?= htmlspecialchars(mb_strimwidth(strip_tags($memo['body']), 0, 80, "...")) ?></td>
                    <td><?= htmlspecialchars($memo['from']) ?> (<?= htmlspecialchars($memo['fullname']) ?>)</td>
                    <td><?= date('M d, Y H:i', strtotime($memo['created_at'])) ?></td>
                    <td class="actions-cell">
                        <!-- Retrieve Button -->
                        <button type="button" class="action-btn retrieve-btn" data-id="<?= $memo['id'] ?>" title="Retrieve">
                            <i class="fas fa-undo"></i>
                        </button>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<!-- DataTables JS -->
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>

<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
$(document).ready(function () {
    // Show SweetAlert2 message from session
    <?php if ($msg === 'retrieved'): ?>
        Swal.fire({ icon: 'success', title: 'Retrieved!', text: 'Memorandum restored successfully.' });
    <?php endif; ?>

    // Initialize DataTable
    $('#archivedMemosTable').DataTable({
        "order": [[4, "desc"]],
        "columnDefs": [
            { "orderable": false, "targets": [5] },
            { "searchable": false, "targets": [5] }
        ],
        "language": {
            "emptyTable": "No archived memorandums to show.",
            "search": "Search:",
            "lengthMenu": "Show _MENU_ entries",
            "info": "Showing _START_ to _END_ of _TOTAL_ archived memos"
        },
        "responsive": true,
        "autoWidth": false
    });

    // ✅ Handle Retrieve with SweetAlert2 + POST
    $('#archivedMemosTable tbody').on('click', '.retrieve-btn', function () {
        const memoId = $(this).data('id');

        Swal.fire({
            title: 'Retrieve Memorandum?',
            text: "This will restore the memo to your inbox.",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#28a745',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, retrieve it!',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                const form = $('<form>')
                    .attr('method', 'POST')
                    .append(
                        $('<input>').attr('name', 'memo_id').val(memoId),
                        $('<input>').attr('name', 'retrieve_memo').val('1')
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