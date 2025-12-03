<?php
header('Content-Type: application/json');
// Database connection
$servername = "127.0.0.1";
$username = "root";
$password = "10148570";
$dbname = "smssystem"; 

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? '';

    if (!$id) {
        echo json_encode(["status" => "error", "message" => "Missing pupil ID"]);
        exit;
    }

    $stmt = $conn->prepare("DELETE FROM students WHERE id = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        echo json_encode(["status" => "success", "message" => "Pupil deleted successfully"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Failed to delete pupil"]);
    }

    $stmt->close();
    $conn->close();
} else {
    echo json_encode(["status" => "error", "message" => "Invalid request method"]);
}
