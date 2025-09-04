<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";
include "../includes/admin_sidebar.php";
require_once 'auth_admin.php';

if ($_SESSION['role'] !== 'admin') {
    die("Access denied. Admins only.");
}
// Handle Add Department
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["add_department"])) {
    $name = trim($_POST["department_name"]);
    if ($name !== "") {
        $stmt = $conn->prepare("INSERT INTO departments (name) VALUES (?)");
        $stmt->bind_param("s", $name);
        $stmt->execute();
        $stmt->close();
        header("Location: department.php");
        exit;
    }
}

// Handle Edit Department
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["edit_department"])) {
    $id = intval($_POST["department_id"]);
    $name = trim($_POST["department_name"]);
    if ($id && $name !== "") {
        $stmt = $conn->prepare("UPDATE departments SET name=? WHERE id=?");
        $stmt->bind_param("si", $name, $id);
        $stmt->execute();
        $stmt->close();
        header("Location: department.php?msg=updated");
        exit;
    }
}

// Handle Delete Department
if (isset($_GET["delete"])) {
    $id = intval($_GET["delete"]);
    if ($id) {
        $stmt = $conn->prepare("DELETE FROM departments WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        header("Location: department.php");
        exit;
    }
}

// Fetch all departments
$res = $conn->query("SELECT * FROM departments ORDER BY name ASC");
$departments = $res->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Departments - Admin Panel</title>
    <link rel="stylesheet" href="../includes/user_style.css">
    <style>
        body { background: #f5f5f5; }
        .container { max-width: 800px; margin: 40px auto; background: #fff; padding: 20px; border-radius: 8px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        table th, table td { padding: 10px; border: 1px solid #ddd; text-align: left; }
        .btn { padding: 6px 12px; border-radius: 4px; background: #007bff; color: #fff; text-decoration: none; }
        .btn:hover { background: #0056b3; }
        .btn-danger { background: #dc3545; }
        .btn-danger:hover { background: #a71d2a; }
        .btn-secondary { background: #6c757d; }
        .btn-secondary:hover { background: #565e64; }
    </style>
</head>
<body>
<div class="container">
    <h2>Departments Management</h2>

    <?php if (isset($_GET['msg'])): ?>
        <div style="color:green;font-weight:bold;">
            Department <?= htmlspecialchars($_GET['msg']) ?> successfully!
        </div>
    <?php endif; ?>

    <!-- Add Department Form -->
    <form method="post" style="margin-bottom:20px;">
        <label for="department_name">Add New Department:</label>
        <input type="text" name="department_name" required>
        <button type="submit" name="add_department" class="btn">Add</button>
    </form>

    <!-- Department List -->
    <table>
        <tr>
            <th>ID</th>
            <th>Department Name</th>
            <th>Actions</th>
        </tr>
        <?php foreach ($departments as $dept): ?>
        <tr>
            <td><?= $dept['id'] ?></td>
            <td><?= htmlspecialchars($dept['name']) ?></td>
            <td>
                <!-- Edit Form -->
                <form method="post" style="display:inline;">
                    <input type="hidden" name="department_id" value="<?= $dept['id'] ?>">
                    <input type="text" name="department_name" value="<?= htmlspecialchars($dept['name']) ?>" required>
                    <button type="submit" name="edit_department" class="btn btn-secondary">Update</button>
                </form>
                <a href="department.php?delete=<?= $dept['id'] ?>" class="btn btn-danger" onclick="return confirm('Delete this department?');">Delete</a>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>

    <div style="margin-top:20px;">
        <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
    </div>
</div>
<?php include "../includes/admin_footer.php"; ?>
</body>
</html>
