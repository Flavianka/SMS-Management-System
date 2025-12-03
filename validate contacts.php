<?php
// validate_contacts.php
header("Content-Type: application/json");

// DB connection
$servername = "127.0.0.1";
$username = "root";
$password = "10148570";
$dbname = "smssystem";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    echo json_encode([
        "status" => "error",
        "message" => "DB connection failed"
    ]);
    exit;
}

// Remove spaces first (optional cleanup)
$conn->query("UPDATE guardians SET phone_number = REPLACE(phone_number, ' ', '')");

// Query invalid numbers
$sql = "SELECT id, guardian_name, phone_number
        FROM guardians
        WHERE phone_number REGEXP '[^0-9]'  -- contains invalid characters
           OR LENGTH(phone_number) <> 12";  // wrong length

$result = $conn->query($sql);

$invalid = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $invalid[] = $row;
    }
}

echo json_encode([
    "status" => "success",
    "count" => count($invalid),
    "invalid_numbers" => $invalid
], JSON_PRETTY_PRINT);

$conn->close();
