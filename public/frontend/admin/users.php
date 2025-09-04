<?php

require_once "../includes/db.php";
include "../includes/admin_sidebar.php";
require_once 'auth_admin.php';

if ($_SESSION['role'] !== 'admin') {
    die("Access denied. Admins only.");
}
// Pagination & Search
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 10;
$offset = ($page - 1) * $per_page;

$search = trim($_GET['search'] ?? '');
$where = "1=1";
if ($search) {
    $safe_search = $conn->real_escape_string($search);
    $where .= " AND (fullname LIKE '%$safe_search%' OR username LIKE '%$safe_search%' OR email LIKE '%$safe_search%' OR contact LIKE '%$safe_search%' OR address LIKE '%$safe_search%')";
}

$total = $conn->query("SELECT COUNT(*) FROM users WHERE $where")->fetch_row()[0];
$users = $conn->query("SELECT * FROM users WHERE $where ORDER BY fullname ASC LIMIT $per_page OFFSET $offset");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>All Users - Admin Panel</title>

    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css" rel="stylesheet" />

    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet" />

    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
            margin: 0;
            padding: 0;
        }

        .container {
            max-width: 1200px;
            margin: auto;
            padding: 20px;
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            margin-top: 20px;
        }

        h2 {
            color: #2c3e50;
            margin-bottom: 15px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s ease;
            margin-right: 10px;
        }

        .btn-primary {
            background-color: #007bff;
            color: white;
        }

        .btn-primary:hover {
            background-color: #0056b3;
        }

        .btn-secondary {
            background-color: #6c79e8;
            color: white;
        }

        .btn-secondary:hover {
            background-color: #5a68d4;
        }

        /* Search Form */
        form {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            flex-wrap: wrap;
            gap: 10px;
        }

        input[type="text"] {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            width: 250px;
            font-size: 14px;
        }

        button[type="submit"] {
            padding: 8px 12px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        button[type="submit"]:hover {
            background-color: #0056b3;
        }

        /* Table Styling */
        table.dataTable {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        th {
            background-color: #6c79e8;
            color: white;
            padding: 12px 15px;
            text-align: left;
            font-weight: 500;
            border-bottom: 1px solid #ddd;
        }

        td {
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
            vertical-align: middle;
        }

        tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        tr:hover {
            background-color: #f0f4ff;
        }

        /* Actions Column */
        .actions-cell {
            display: flex;
            gap: 10px;
            justify-content: center;
        }

        .action-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 24px;
            height: 24px;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .edit {
            background-color: #007bff;
            color: white;
        }

        .view {
            background-color: #28a745;
            color: white;
        }

        .delete {
            background-color: #dc3545;
            color: white;
        }

        .action-icon:hover {
            transform: scale(1.1);
        }

        /* Memos Link */
        .memos-link {
            display: flex;
            align-items: center;
            gap: 4px;
            color: #007bff;
            text-decoration: none;
            font-size: 14px;
        }

        .memos-link:hover {
            text-decoration: underline;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .container {
                margin: 10px;
                padding: 10px;
            }

            .btn {
                width: 100%;
                margin-bottom: 10px;
            }

            form {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <h2>All Users</h2>

   <!-- <form method="get">
        <input type="text" name="search" placeholder="Search users..." value="<?= htmlspecialchars($search) ?>">
        <button type="submit">Search</button>
    </form> !-->

    <div style="display: flex; gap: 10px; margin-bottom: 15px;">
        <a href="user_add.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> Add User
        </a>
    </div>

    <div style="overflow-x: auto;">
        <table id="usersTable" class="display" style="width: 100%;">
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
                <?php while($user = $users->fetch_assoc()): ?>
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
                    <td><?= htmlspecialchars($user['role']) ?></td>
                    <td class="memos-cell">
                        <?php
                        $count = $conn->query("SELECT COUNT(*) FROM memos WHERE user_id=" . intval($user['id']))->fetch_row()[0];
                        ?>
                        <span><?= $count ?></span>
                        <a href="memos.php?user_id=<?= $user['id'] ?>" class="memos-link">
                            <i class="fas fa-eye"></i>
                        </a>
                    </td>
                    <td class="actions-cell">
                        <a href="user_edit.php?id=<?= $user['id'] ?>" class="action-icon edit" title="Edit">
                            <i class="fas fa-edit"></i>
                        </a>
                        <a href="user_delete.php?id=<?= $user['id'] ?>" 
                           class="action-icon delete" 
                           title="Delete"
                           onclick="return confirm('Delete this user and their memos?');">
                            <i class="fas fa-trash-alt"></i>
                        </a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <div style="margin-top: 15px; color: #666; font-size: 14px;">
        Showing <?= ($page - 1) * $per_page + 1 ?> to <?= min($page * $per_page, $total) ?> of <?= $total ?> entries.
    </div>
</div>

<?php include "../includes/admin_footer.php"; ?>

<!-- jQuery & DataTables -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>

<script>
$(document).ready(function() {
    $('#usersTable').DataTable({
        "pageLength": 10,
        "order": [[0, "asc"]],
        "columnDefs": [
            { "orderable": false, "targets": [8, 9] },
            { "searchable": false, "targets": [9] }
        ],
        "language": {
            "emptyTable": "No users found.",
            "search": "Search:",
            "lengthMenu": "Show _MENU_ entries",
            "info": "Showing _START_ to _END_ of _TOTAL_ entries",
            "paginate": {
                "previous": "Previous",
                "next": "Next"
            }
        },
        "drawCallback": function() {
            // Optional: Reapply styles after draw
        }
    });
});
</script>

</body>
</html>