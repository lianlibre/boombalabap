<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once 'auth_admin.php';

// 🔐 Admin-only access
if ($_SESSION['role'] !== 'admin') {
    die("Access denied. Admins only.");
}

// 🔍 Search functionality
$search = trim($_GET['search'] ?? '');
$where = "1=1";
if ($search) {
    $safe_search = $conn->real_escape_string($search);
    $where .= " AND (
        fullname LIKE '%$safe_search%' OR 
        username LIKE '%$safe_search%' OR 
        email LIKE '%$safe_search%' OR 
        contact LIKE '%$safe_search%' OR 
        address LIKE '%$safe_search%'
    )";
}

// Fetch all users (let DataTables handle sorting/pagination)
$sql = "SELECT * FROM users WHERE $where ORDER BY fullname ASC";
$users = $conn->query($sql);

// Total count
$total_result = $conn->query("SELECT COUNT(*) as total FROM users WHERE $where");
$total = $total_result->fetch_assoc()['total'];

include "../includes/admin_sidebar.php";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>All Users - Admin Panel</title>

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500&display=swap" rel="stylesheet">

    <!-- DataTables CSS + Responsive Extension -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css" />
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.dataTables.min.css" />

    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />

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
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
            background-color: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
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
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            transition: background 0.3s ease;
        }

        .btn:hover {
            background-color: #0056b3;
        }

        /* Table Container */
        .table-container {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            margin-top: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border: 1px solid #ddd;
        }

        table.dataTable {
            width: 100% !important;
            table-layout: auto; /* Prevent fixed layout collapse */
        }

        table.dataTable th,
        table.dataTable td {
            white-space: nowrap;
            padding: 10px 12px;
            vertical-align: middle;
        }

        table.dataTable th {
            background-color: #ecf0f1;
            color: #2c3e50;
            font-weight: 500;
            font-size: 14px;
        }

        table.dataTable tr:hover {
            background-color: #f8f9fa;
        }

        /* Actions Cell */
        .actions-cell {
            display: flex;
            justify-content: center;
            gap: 10px;
            min-width: 100px;
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
        }

        .action-icon.edit { color: #007bff; }
        .action-icon.view { color: #28a745; }
        .action-icon.delete { color: #dc3545; }

        .action-icon:hover {
            transform: scale(1.1);
            box-shadow: 0 0 5px rgba(0,0,0,0.2);
        }

        /* Memos Link */
        .memos-link {
            color: #007bff;
            text-decoration: none;
            margin-left: 6px;
            font-size: 13px;
        }

        .memos-link:hover {
            text-decoration: underline;
        }

        .memos-cell {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        /* Tooltip / Truncate long text */
        td[title] {
            max-width: 150px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Responsive adjustments via CSS */
        @media (max-width: 768px) {
            .container {
                margin: 10px;
                padding: 15px;
            }

            .btn span {
                display: none;
            }

            .btn i {
                font-size: 1.2rem;
            }

            /* Force smaller font in table */
            table.dataTable th,
            table.dataTable td {
                font-size: 13px;
                padding: 8px 10px;
            }

            .actions-cell {
                justify-content: flex-start;
            }

            .action-icon {
                width: 28px;
                height: 28px;
                font-size: 13px;
            }
        }
    </style>
</head>
<body>
<div class="container">
    <h2>👥 All Users</h2>

    <a href="user_add.php" class="btn">
        <i class="fas fa-plus"></i> <span>Add User</span>
    </a>

    <!-- Responsive Table Wrapper -->
    <div class="table-container">
        <table id="usersTable" class="display responsive nowrap" style="width:100%">
            <thead>
                <tr>
                    <th>Full Name</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Contact</th>
                    <th>Birthday</th>
                    <th>Gender</th>
                    <th>Address</th>
                    <th>Role</th>
                    <th>Memos</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($user = $users->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($user['fullname']) ?></td>
                    <td><?= htmlspecialchars($user['username'] ?? "-") ?></td>
                    <td title="<?= htmlspecialchars($user['email']) ?>">
                        <?= htmlspecialchars(mb_strimwidth($user['email'], 0, 30, "...")) ?>
                    </td>
                    <td><?= htmlspecialchars($user['contact'] ?? "-") ?></td>
                    <td><?= htmlspecialchars($user['birthday'] ?? "-") ?></td>
                    <td><?= htmlspecialchars($user['gender'] ?? "-") ?></td>
                    <td title="<?= htmlspecialchars($user['address'] ?? "-") ?>">
                        <?= htmlspecialchars(mb_strimwidth($user['address'] ?? "-", 0, 30, "...")) ?>
                    </td>
                    <td><strong><?= ucfirst(htmlspecialchars($user['role'])) ?></strong></td>
                    <td class="memos-cell">
                        <?php
                        $stmt_count = $conn->prepare("SELECT COUNT(*) FROM memos WHERE user_id = ?");
                        $stmt_count->bind_param("i", $user['id']);
                        $stmt_count->execute();
                        $count = $stmt_count->get_result()->fetch_row()[0];
                        $stmt_count->close();
                        ?>
                        <span><?= $count ?></span>
                        <a href="memos.php?user_id=<?= $user['id'] ?>" class="memos-link" title="View memos">
                            <i class="fas fa-eye"></i>
                        </a>
                    </td>
                    <td class="actions-cell">
                        <a href="user_edit.php?id=<?= $user['id'] ?>" class="action-icon edit" title="Edit User">
                            <i class="fas fa-edit"></i>
                        </a>
                        <button type="button" class="action-icon delete delete-btn"
                                data-id="<?= $user['id'] ?>"
                                data-name="<?= htmlspecialchars($user['fullname'], ENT_QUOTES) ?>"
                                title="Delete User">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <div style="margin-top: 15px; color: #666; font-size: 14px;">
        <strong><?= number_format($total) ?></strong> user(s) in total.
    </div>
</div>

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<!-- DataTables JS + Responsive Extension -->
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>

<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
$(document).ready(function () {
    // Initialize DataTable with Responsive plugin
    $('#usersTable').DataTable({
        "order": [[0, "asc"]],
        "pageLength": 10,
        "autoWidth": false,
        "responsive": true,  // ← Enables responsive behavior
        "columnDefs": [
            { "className": "control", "orderable": false, "targets": -1 }, // Show expand icon on mobile
            { "orderable": false, "targets": [8, 9] },
            { "searchable": false, "targets": [9] }
        ],
        "language": {
            "emptyTable": "No users found.",
            "search": "Search:",
            "lengthMenu": "Show _MENU_ entries",
            "info": "Showing _START_ to _END_ of _TOTAL_ users"
        }
    });

    // Handle Delete with SweetAlert2
    $('#usersTable tbody').on('click', '.delete-btn', function () {
        const userId = $(this).data('id');
        const userName = $(this).data('name');

        Swal.fire({
            title: 'Delete this user?',
            text: `Are you sure you want to delete "${userName}"? This will also delete their memos.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, delete them!',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                const form = $('<form>')
                    .attr('method', 'POST')
                    .attr('action', 'user_delete.php')
                    .append(
                        $('<input>').attr('name', 'user_id').val(userId),
                        $('<input>').attr('name', 'confirm_delete').val('1')
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