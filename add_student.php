<?php
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
    $first_name = $_POST['first_name'];
    $last_name = $_POST['last_name'];
    $admission_number = $_POST['admission_number'];
    $grade_level = $_POST['grade_level'];
    $stream = $_POST['stream'];
    $boarding_status = $_POST['boarding_status'];

    $sql = "INSERT INTO students (first_name, last_name, admission_number, grade_level, stream, boarding_status) VALUES ('$first_name', '$last_name', '$admission_number', '$grade_level', '$stream', '$boarding_status')";
    if ($conn->query($sql) === TRUE) {
        echo json_encode(["status" => "success", "message" => "Student added successfully!"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Error: " . $conn->error]);
    }
    $conn->close();
}
?>
