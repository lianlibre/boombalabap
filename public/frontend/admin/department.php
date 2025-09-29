<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once 'auth_admin.php';

// 🔒 Admin-only access
if ($_SESSION['role'] !== 'admin') {
    die("Access denied. Admins only.");
}

// ✅ Handle Add Department
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["add_department"])) {
    $name = trim($_POST["department_name"]);
    if ($name !== "") {
        $stmt = $conn->prepare("INSERT INTO departments (name) VALUES (?)");
        $stmt->bind_param("s", $name);
        if ($stmt->execute()) {
            $_SESSION['msg'] = "added";
        } else {
            $_SESSION['msg'] = "error";
        }
        $stmt->close();
        header("Location: department.php");
        exit;
    }
}

// ✅ Handle Edit Department
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["edit_department"])) {
    $id = intval($_POST["department_id"]);
    $name = trim($_POST["department_name"]);
    if ($id && $name !== "") {
        $stmt = $conn->prepare("UPDATE departments SET name=? WHERE id=?");
        $stmt->bind_param("si", $name, $id);
        if ($stmt->execute()) {
            $_SESSION['msg'] = "updated";
        } else {
            $_SESSION['msg'] = "error";
        }
        $stmt->close();
        header("Location: department.php");
        exit;
    }
}

// ✅ Handle Delete via POST
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["delete_department"])) {
    $id = intval($_POST["department_id"]);
    if ($id > 0) {
        $stmt = $conn->prepare("DELETE FROM departments WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $_SESSION['msg'] = "deleted";
        } else {
            $_SESSION['msg'] = "error";
        }
        $stmt->close();
        header("Location: department.php");
        exit;
    }
}

// ✅ Fetch all departments
$res = $conn->query("SELECT * FROM departments ORDER BY name ASC");
$departments = $res->fetch_all(MYSQLI_ASSOC);

// ✅ Get session message
$msg = '';
if (isset($_SESSION['msg'])) {
    $msg = $_SESSION['msg'];
    unset($_SESSION['msg']);
}

// ✅ Include sidebar AFTER logic to prevent header issues
include "../includes/admin_sidebar.php";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Departments - Admin Panel</title>

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">

    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css" />

    <!-- SweetAlert2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" />

    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />

    <style>
        /* Global Styles */
        body {
            font-family: 'Roboto', Arial, sans-serif;
            background: #f4f6f9;
            margin: 0;
            padding: 0;
            color: #333;
        }

        .container {
            max-width: 1000px;
            margin: 20px auto;
            background: #fff;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        h2 {
            text-align: center;
            color: #2c3e50;
            margin-bottom: 25px;
            font-weight: 500;
        }

        /* Form Styling */
        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 500;
            color: #2c3e50;
        }

        .form-group input[type="text"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #bdc3c7;
            border-radius: 6px;
            font-size: 14px;
            box-sizing: border-box;
        }

        .form-group input[type="text"]:focus {
            border-color: #3498db;
            outline: none;
            box-shadow: 0 0 5px rgba(52, 152, 219, 0.2);
        }

        /* Button Styling */
        .btn {
            padding: 10px 14px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.3s ease;
        }

        .btn i {
            font-size: 0.9rem;
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

        .btn-danger {
            background: #e74c3c;
            color: #fff;
        }
        .btn-danger:hover {
            background: #c0392b;
        }

        /* Responsive Buttons on Small Screens */
        .actions-cell {
            white-space: nowrap;
        }

        .actions-cell .btn {
            margin: 2px 0;
            font-size: 13px;
            padding: 6px 10px;
        }

        /* Table Styling */
        table {
            width: 100%;
            margin-top: 20px;
            border-collapse: collapse;
        }

        table th, table td {
            padding: 12px 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        table th {
            background-color: #ecf0f1;
            color: #2c3e50;
            font-weight: 500;
        }

        table tr:hover {
            background-color: #f8f9fa;
        }

        /* Responsive Table Wrapper */
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        /* Back Link */
        .back-container {
            text-align: center;
            margin-top: 30px;
        }

        /* Mobile Adjustments */
        @media (max-width: 600px) {
            .container {
                margin: 10px;
                padding: 15px;
            }

            h2 {
                font-size: 1.5rem;
            }

            .form-group input[type="text"],
            .btn {
                font-size: 14px;
            }

            .btn i {
                font-size: 1rem;
            }

            table th:nth-child(3),
            table td:nth-child(3) {
                width: 30%;
            }

            .actions-cell .btn span {
                display: none;
            }

            .actions-cell .btn i {
                margin: 0;
            }

            .actions-cell .btn {
                padding: 8px;
                min-width: 36px;
                justify-content: center;
            }

            .btn-primary {
                width: 100%;
                justify-content: center;
                margin-top: 10px;
            }
        }
    </style>
</head>
<body>
<div class="container">
    <h2>📋 Departments Management</h2>

    <!-- Add Department Form -->
    <form method="post" style="margin-bottom: 25px;">
        <div class="form-group">
            <label for="department_name">Add New Department:</label>
            <input type="text" name="department_name" placeholder="Enter department name" required>
        </div>
        <button type="submit" name="add_department" class="btn btn-primary">
            <i class="fas fa-plus"></i> <span>Add Department</span>
        </button>
    </form>

    <!-- Responsive Table Wrapper -->
    <div class="table-responsive">
        <table id="departmentsTable" class="display">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Department Name</th>
                    <th class="actions-cell">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($departments as $dept): ?>
                <tr>
                    <td><?= htmlspecialchars($dept['id']) ?></td>
                    <td><?= htmlspecialchars($dept['name']) ?></td>
                    <td class="actions-cell">
                        <button class="btn btn-secondary edit-btn"
                                data-id="<?= $dept['id'] ?>"
                                data-name="<?= htmlspecialchars($dept['name'], ENT_QUOTES) ?>">
                            <i class="fas fa-edit"></i> <span>Edit</span>
                        </button>

                        <button class="btn btn-danger delete-btn"
                                data-id="<?= $dept['id'] ?>"
                                data-name="<?= htmlspecialchars($dept['name'], ENT_QUOTES) ?>">
                            <i class="fas fa-trash"></i> <span>Delete</span>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Back Button -->
    <div class="back-container">
        <a href="dashboard.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> <span>Back to Dashboard</span>
        </a>
    </div>
</div>

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<!-- DataTables JS -->
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>

<!-- SweetAlert2 JS -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
$(document).ready(function () {
    // Show SweetAlert2 message from PHP session
    <?php if ($msg === 'added'): ?>
        Swal.fire({ icon: 'success', title: 'Success!', text: 'Department added successfully!' });
    <?php elseif ($msg === 'updated'): ?>
        Swal.fire({ icon: 'success', title: 'Updated!', text: 'Department updated successfully!' });
    <?php elseif ($msg === 'deleted'): ?>
        Swal.fire({ icon: 'success', title: 'Deleted!', text: 'Department deleted successfully!' });
    <?php elseif ($msg === 'error'): ?>
        Swal.fire({ icon: 'error', title: 'Oops...', text: 'An error occurred. Please try again.' });
    <?php endif; ?>

    // Initialize DataTable with responsive settings
    $('#departmentsTable').DataTable({
        "order": [[1, "asc"]],
        "pageLength": 10,
        "language": {
            "search": "Search:",
            "lengthMenu": "Show _MENU_ entries",
            "info": "Showing _START_ to _END_ of _TOTAL_ departments",
            "paginate": {
                "previous": "‹",
                "next": "›"
            }
        },
        "responsive": true,
        "autoWidth": false,
        "drawCallback": function () {
            // Re-attach event listeners after pagination, search, etc.
        }
    });

    // ✅ Use event delegation for dynamic rows
    $('#departmentsTable tbody').on('click', '.edit-btn', function () {
        const id = $(this).data('id');
        let name = $(this).data('name');

        Swal.fire({
            title: 'Edit Department',
            input: 'text',
            inputLabel: 'Department Name',
            inputValue: name,
            showCancelButton: true,
            confirmButtonText: 'Update',
            cancelButtonText: 'Cancel',
            preConfirm: (newName) => {
                if (!newName || !newName.trim()) {
                    Swal.showValidationMessage('Please enter a valid name');
                    return false;
                }
                return { id: id, name: newName.trim() };
            }
        }).then(result => {
            if (result.isConfirmed) {
                const form = $('<form>')
                    .attr('method', 'POST')
                    .append(
                        $('<input>').attr('name', 'department_id').val(result.value.id),
                        $('<input>').attr('name', 'department_name').val(result.value.name),
                        $('<input>').attr('name', 'edit_department').val('1')
                    )
                    .hide()
                    .appendTo('body')
                    .submit();
            }
        });
    });

    // ✅ Delete with delegation
    $('#departmentsTable tbody').on('click', '.delete-btn', function () {
        const id = $(this).data('id');
        const name = $(this).data('name');

        Swal.fire({
            title: 'Are you sure?',
            text: `Do you want to delete "${name}"? This action cannot be undone.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, delete it!',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                const form = $('<form>')
                    .attr('method', 'POST')
                    .append(
                        $('<input>').attr('name', 'department_id').val(id),
                        $('<input>').attr('name', 'delete_department').val('1')
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