<?php
session_start();
header('Content-Type: application/json');

if (isset($_SESSION['user_id'])) {
    echo json_encode([
        "status" => "ok",
        "user_id" => $_SESSION['user_id'],
        "username" => $_SESSION['username'],
        "role" => $_SESSION['role']
    ]);
} else {
    echo json_encode([
        "status" => "error",
        "message" => "Not logged in"
    ]);
}
?>
