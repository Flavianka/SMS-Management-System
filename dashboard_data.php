<?php
// dashboard-data.php
header("Content-Type: application/json");

// Database connection
$host = "127.0.0.1";
$user = "root";      
$pass = "10148570";
$db   = "smssystem";

$conn = new mysqli($host, $user, $pass, $db);

// Check connection
if ($conn->connect_error) {
    echo json_encode(["status" => "error", "message" => $conn->connect_error]);
    exit;
}

// Queries
$totalMessages = $conn->query("SELECT COUNT(*) AS count FROM messages")->fetch_assoc()['count'];
$totalStudents = $conn->query("SELECT COUNT(*) AS count FROM students")->fetch_assoc()['count'];
$totalGuardians = $conn->query("SELECT COUNT(*) AS count FROM guardians")->fetch_assoc()['count'];
$activeGrades = $conn->query("SELECT COUNT(DISTINCT grade_level) AS count FROM students")->fetch_assoc()['count'];

// Return JSON
echo json_encode([
    "status" => "success",
    "data" => [
        "messages" => $totalMessages,
        "students" => $totalStudents,
        "guardians" => $totalGuardians,
        "grades" => $activeGrades
    ]
]);

$conn->close();
