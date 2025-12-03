<?php
// search_guardians.php
header("Content-Type: application/json; charset=utf-8");

// DB connection (mysqli)
$servername = "127.0.0.1";
$username   = "root";
$password   = "10148570";
$dbname     = "smssystem";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "DB connection failed"]);
    exit;
}

// read JSON body
$input = json_decode(file_get_contents("php://input"), true);
$query = trim($input["query"] ?? "");

if ($query === "") {
    echo json_encode(["status" => "success", "guardians" => []]);
    exit;
}

// prepared statement joining guardians -> students
$sql = "
    SELECT g.guardian_name, g.phone_number, s.first_name, s.last_name, s.grade_level, s.stream
    FROM guardians g
    INNER JOIN students s ON g.student_id = s.id
    WHERE s.first_name LIKE ? OR s.last_name LIKE ? OR CONCAT(s.first_name, ' ', s.last_name) LIKE ?
    ORDER BY s.last_name, s.first_name
    LIMIT 20
";
$like = "%{$query}%";
$stmt = $conn->prepare($sql);
$stmt->bind_param("sss", $like, $like, $like);
$stmt->execute();
$result = $stmt->get_result();

$rows = [];
while ($r = $result->fetch_assoc()) {
    $rows[] = $r;
}

echo json_encode(["status" => "success", "guardians" => $rows]);
$stmt->close();
$conn->close();
