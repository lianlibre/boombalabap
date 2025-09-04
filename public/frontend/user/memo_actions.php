<?php
require_once "../includes/auth.php";
require_once "../includes/db.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['id']);
    $action = $_POST['action'];

    if ($action === 'archive') {
        $stmt = $conn->prepare("UPDATE memos SET archived = 1 WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        echo "Archived";
    } elseif ($action === 'retrieve') {
        $stmt = $conn->prepare("UPDATE memos SET archived = 0 WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        echo "Retrieved";
    }
}
