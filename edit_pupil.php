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
    // collect posted data safely
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $admission_number = $_POST['admission_number'] ?? '';
    $first_name = $_POST['first_name'] ?? '';
    $last_name = $_POST['last_name'] ?? '';
    $grade_level = $_POST['grade_level'] ?? '';
    $stream = $_POST['stream'] ?? '';
    $boarding_status = $_POST['boarding_status'] ?? '';

    if ($id <= 0) {
        echo json_encode(["status" => "error", "message" => "Invalid pupil ID"]);
        exit;
    }

    $sql = "UPDATE students 
            SET admission_number=?, first_name=?, last_name=?, grade_level=?, stream=?, boarding_status=? 
            WHERE id=?";
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        echo json_encode(["status" => "error", "message" => "Failed to prepare statement"]);
        exit;
    }

    $stmt->bind_param("ssssssi", 
        $admission_number, 
        $first_name, 
        $last_name, 
        $grade_level, 
        $stream, 
        $boarding_status, 
        $id
    );

    if ($stmt->execute()) {
        echo json_encode(["status" => "success", "message" => "Pupil updated successfully"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Failed to update pupil"]);
    }

    $stmt->close();
    $conn->close();
} else {
    echo json_encode(["status" => "error", "message" => "Invalid request method"]);
}
