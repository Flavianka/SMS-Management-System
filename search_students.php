<?php
// Database connection
$servername = "127.0.0.1";
$username = "root";
$password = "10148570";
$dbname = "smssystem"; 

$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

header("Content-Type: application/json");

$query = $_POST["query"] ?? "";

if (strlen($query) < 2) {
    echo json_encode(["status" => "error", "students" => []]);
    exit;
}

$sql = "SELECT id, first_name, last_name, grade_level, stream 
        FROM students 
        WHERE first_name LIKE ? OR last_name LIKE ?
        LIMIT 10";
$stmt = $conn->prepare($sql);
$searchTerm = "%" . $query . "%";
$stmt->bind_param("ss", $searchTerm, $searchTerm);
$stmt->execute();
$result = $stmt->get_result();

$students = [];
while ($row = $result->fetch_assoc()) {
    $students[] = $row;
}

echo json_encode(["status" => "success", "students" => $students]);
