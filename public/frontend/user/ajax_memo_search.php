<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";

$user_id = $_SESSION['user_id'];
$user = $conn->query("SELECT * FROM users WHERE id=$user_id")->fetch_assoc();
$user_department = $user['department']; 

$search = $_GET['search'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$archived = intval($_GET['archived'] ?? 0);
$limit = 10;
$offset = ($page - 1) * $limit;

// Build query with department and search
$sql = "SELECT m.*, u.department 
        FROM memos m 
        JOIN users u ON m.user_id = u.id
        JOIN memo_recipients r ON m.id = r.memo_id
        WHERE (r.department = ? OR r.department = 'All')
          AND m.archived = ?";

$params = [$user_department, $archived];
$types = "si";

if ($search) {
    $sql .= " AND (m.subject LIKE ? OR m.body LIKE ? OR u.department LIKE ? OR r.department LIKE ?)";
    $search_term = "%$search%";
    array_push($params, $search_term, $search_term, $search_term, $search_term);
    $types .= "ssss";
}

$sql .= " GROUP BY m.id ORDER BY m.created_at DESC LIMIT $limit OFFSET $offset";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$memos = $stmt->get_result();
?>

<style>
/* Unified blue buttons */
.btn-archive,
.btn-retrieve {
    background: #1976d2;
    color: #fff;
    border: none;
    padding: 6px 14px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 0.95rem;
    margin-left: 8px;
    transition: background 0.2s;
}
.btn-archive:hover,
.btn-retrieve:hover {
    background: #1259a7;
}

/* Menu button */
.menu-btn {
    background: #1976d2;
    color: #fff;
    border: none;
    padding: 6px 14px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 0.95rem;
}
.menu-btn:hover {
    background: #1259a7;
}
.menu-content {
    display: none;
    position: absolute;
    z-index: 10;
    background: #fff;
    box-shadow: 0 2px 8px rgba(0,0,0,0.12);
    min-width: 120px;
    border-radius: 4px;
    margin-top: 4px;
}
.menu-content button {
    display: block;
    padding: 8px 14px;
    color: #222;
    background: none;
    border: none;
    width: 100%;
    text-align: left;
    cursor: pointer;
    font-size: 0.9rem;
}
.menu-content button:hover {
    background: #f0f0f0;
}
</style>

<table border="1" cellspacing="0" cellpadding="6" width="100%">
    <tr>
        <th>No.</th>
        <th>Subject</th>
        <th>Body</th>
        <th>To</th>
        <th>From</th>
        <th>Date</th>
        <th>Actions</th>
    </tr>
    <?php 
    $row_num = $offset + 1;
    while ($memo = $memos->fetch_assoc()): 
        $to_query = $conn->query("SELECT department FROM memo_recipients WHERE memo_id=".$memo['id']);
        $to_list = [];
        while($to = $to_query->fetch_assoc()){
            $to_list[] = $to['department'];
        }
    ?>
    <tr id="memo-row-<?= $memo['id'] ?>">
        <td><?= $row_num ?></td>
        <td><?= htmlspecialchars($memo['subject']) ?></td>
        <td><?= htmlspecialchars(mb_strimwidth($memo['body'], 0, 70, "...")) ?></td>
        <td><?= htmlspecialchars(implode(", ", $to_list)) ?></td>
        <td><?= htmlspecialchars($memo['department']) ?></td>
        <td><?= htmlspecialchars($memo['created_at']) ?></td>
        <td>
            <!-- Menu for Preview & Print -->
            <div class="menu-dropdown" style="display:inline-block; position:relative;">
                <button class="menu-btn" onclick="toggleMenu(this)">Menu ▾</button>
                <div class="menu-content">
                    <button onclick="showMemoPreview(<?= $memo['id'] ?>)">Preview</button>
                    <button onclick="printMemo(<?= $memo['id'] ?>)">Print</button>
                </div>
            </div>

            <!-- Archive / Retrieve separate buttons -->
            <?php if ($archived): ?>
                <button class="btn-retrieve" onclick="retrieveMemo(<?= $memo['id'] ?>)">Retrieve</button>
            <?php else: ?>
                <button class="btn-archive" onclick="archiveMemo(<?= $memo['id'] ?>)">Archive</button>
            <?php endif; ?>
        </td>
    </tr>
    <?php 
    $row_num++;
    endwhile; 
    ?>
</table>

<div id="action-notification" style="
    display:none;
    background:#388e3c;
    color:#fff;
    padding:16px;
    margin-top:12px;
    font-weight:bold;
    border-radius:6px;
    text-align:center;
    box-shadow:0 2px 6px rgba(0,0,0,.15);
">
    <span id="action-text"></span>
    <button style="background:none;border:none;color:#fff;font-size:1.2rem;float:right;cursor:pointer;"
        onclick="document.getElementById('action-notification').style.display='none'">&times;</button>
</div>

<script>
function toggleMenu(btn) {
    document.querySelectorAll('.menu-content').forEach(function(menu){
        if(menu !== btn.nextElementSibling) menu.style.display = 'none';
    });
    var menu = btn.nextElementSibling;
    menu.style.display = (menu.style.display === 'block' ? 'none' : 'block');
}

// Archive
function archiveMemo(id) {
    if (!confirm("Archive this memorandum?")) return;
    var xhr = new XMLHttpRequest();
    xhr.open("POST", "memo_actions.php", true);
    xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
    xhr.onload = function () {
        if (xhr.status === 200) {
            document.getElementById("memo-row-" + id).remove();
            showActionNotification("Memo archived successfully!");
        }
    };
    xhr.send("action=archive&id=" + id);
}

// Retrieve
function retrieveMemo(id) {
    if (!confirm("Retrieve this memorandum?")) return;
    var xhr = new XMLHttpRequest();
    xhr.open("POST", "memo_actions.php", true);
    xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
    xhr.onload = function () {
        if (xhr.status === 200) {
            document.getElementById("memo-row-" + id).remove();
            showActionNotification("Memo retrieved successfully!");
        }
    };
    xhr.send("action=retrieve&id=" + id);
}

// Show notification
function showActionNotification(message) {
    var notif = document.getElementById("action-notification");
    document.getElementById("action-text").textContent = message;
    notif.style.display = "block";
    setTimeout(function(){ notif.style.display = "none"; }, 4000);
}

</script>
