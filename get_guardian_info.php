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

$student_id = $_GET["student_id"] ?? "";

if (empty($student_id)) {
    echo json_encode(["status" => "error", "message" => "Student ID required"]);
    exit;
}

// Fetch both guardian info AND student details
$sql = "SELECT g.guardian_name, g.phone_number, s.grade_level, s.stream, s.boarding_status 
        FROM guardians g 
        JOIN students s ON g.student_id = s.id 
        WHERE s.id = ? 
        LIMIT 1";
        
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    echo json_encode([
        "status" => "success", 
        "guardian_name" => $row['guardian_name'],
        "phone_number" => $row['phone_number'],
        "student_details" => [
            "grade_level" => $row['grade_level'],
            "stream" => $row['stream'],
            "boarding_status" => $row['boarding_status']
        ]
    ]);
} else {
    echo json_encode([
        "status" => "error", 
        "message" => "No guardian/student found"
    ]);
}

$conn->close();
?>