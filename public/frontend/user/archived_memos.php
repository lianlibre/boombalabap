<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";

// Retrieve logic (Unarchive)
if (isset($_GET['retrieve']) && is_numeric($_GET['retrieve'])) {
    $retrieve_id = intval($_GET['retrieve']);
    $conn->query("UPDATE memos SET archived = 0 WHERE id = $retrieve_id");
    header("Location: archived_memos.php?msg=retrieved");
    exit;
}

// Pagination for archived memos
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 10;
$offset = ($page - 1) * $per_page;

$total = $conn->query("SELECT COUNT(*) FROM memos WHERE archived=1")->fetch_row()[0];
$memos = $conn->query("SELECT m.*, u.fullname FROM memos m JOIN users u ON m.user_id=u.id WHERE m.archived=1 ORDER BY m.created_at DESC LIMIT $per_page OFFSET $offset");

include "../includes/admin_sidebar.php";
?>

<!DOCTYPE html>
<html>
<head>
    <title>Archived Memorandums - Admin Panel</title>
    <link rel="stylesheet" href="../includes/user_style.css">
    <style>
    .retrieve-link {
        color: #3887e7ff;
        margin-left: 8px;
        text-decoration: none;
        font-weight: bold;
        cursor: pointer;
    }
    .retrieve-link:hover {
        color: #5779f1ff;
        text-decoration: underline;
    }
    </style>
</head>
<body>
<div class="container">
<h2>Archived Memorandums</h2>
<a href="memos.php" class="btn">Back</a>
<?php if (isset($_GET['msg']) && $_GET['msg'] === 'retrieved'): ?>
    <div style="color: #4980bbff;"><b>Memorandum retrieved!</b></div>
<?php endif; ?>
<table>
    <tr>
        <th>Subject</th>
        <th>Body</th>
        <th>User</th>
        <th>Date</th>
        <th>Actions</th>
    </tr>
    <?php while($memo = $memos->fetch_assoc()): ?>
    <tr>
        <td><?= htmlspecialchars($memo['subject']) ?></td>
        <td><?= htmlspecialchars(mb_strimwidth($memo['body'], 0, 70, "...")) ?></td>
        <td><?= htmlspecialchars($memo['fullname']) ?></td>
        <td><?= htmlspecialchars($memo['created_at']) ?></td>
        <td>
            <a href="archived_memos.php?retrieve=<?= $memo['id'] ?>" class="retrieve-link" onclick="return confirm('Retrieve this memorandum?')">Retrieve</a>
        </td>
    </tr>
    <?php endwhile; ?>
</table>
<!-- Pagination -->
<div>
    <?php
    $pages = max(1, ceil($total / $per_page));
    for ($i = 1; $i <= $pages; $i++) {
        $url = "admin_archived_memos.php?page=$i";
        if ($i == $page) echo "<strong>$i</strong> ";
        else echo "<a href='$url'>$i</a> ";
    }
    ?>
</div>
</div>
<?php include "../includes/admin_footer.php"; ?>