<?php
// get_pupils.php
// Database connection
$servername = "127.0.0.1";
$username = "root";
$password = "10148570";
$dbname = "smssystem"; 

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

header("Content-Type: application/json");

// Fetch filters from query params
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$grade = isset($_GET['grade']) ? trim($_GET['grade']) : '';
$stream = isset($_GET['stream']) ? trim($_GET['stream']) : '';
$boarding = isset($_GET['boarding']) ? trim($_GET['boarding']) : '';

// Base query
$sql = "SELECT id, first_name, last_name, admission_number, grade_level, stream, boarding_status FROM students WHERE 1=1";
$params = [];
$types = "";

// Search filter
if ($search !== '') {
    $sql .= " AND (first_name, last_name LIKE ? OR admission_number LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $types .= "ss";
}

// Grade filter
if ($grade !== '') {
    $sql .= " AND grade_level = ?";
    $params[] = $grade;
    $types .= "s";
}

// Stream filter
if ($stream !== '') {
    $sql .= " AND stream = ?";
    $params[] = $stream;
    $types .= "s";
}

// Boarding filter
if ($boarding !== '') {
    $sql .= " AND boarding_status = ?";
    $params[] = $boarding;
    $types .= "s";
}

$stmt = $conn->prepare($sql);

// Bind parameters dynamically if any
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();
$pupils = $result->fetch_all(MYSQLI_ASSOC);

echo json_encode($pupils);
