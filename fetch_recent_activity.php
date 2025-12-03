<?php
// Database connection
$host = "127.0.0.1";
$user = "root";
$pass = "10148570";
$dbname = "smssystem";

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch last 5 messages
$sql = "SELECT id, content, grade_level, stream, boarding_status, sent_at, status 
        FROM messages 
        ORDER BY sent_at DESC 
        LIMIT 5";

$result = $conn->query($sql);

$activities = [];

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $activities[] = $row;
    }
}

echo json_encode($activities);
$conn->close();
?>
