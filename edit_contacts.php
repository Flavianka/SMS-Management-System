<?php
// edit_contacts.php
header("Content-Type: application/json");

// Database connection
$servername = "127.0.0.1";
$username = "root";
$password = "10148570"; 
$dbname = "smssystem";

$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    echo json_encode(["status" => "error", "message" => "DB Connection failed"]);
    exit;
}

// Make sure required fields exist
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? '';
    $guardian_name = $_POST['guardian_name'] ?? '';
    $phone_number = $_POST['phone_number'] ?? '';

    if (empty($id) || empty($guardian_name) || empty($phone_number)) {
        echo json_encode(["status" => "error", "message" => "All fields required"]);
        exit;
    }

    // Prepare and execute update
    $stmt = $conn->prepare("UPDATE guardians SET guardian_name = ?, phone_number = ? WHERE id = ?");
    if (!$stmt) {
        echo json_encode(["status" => "error", "message" => "Prepare failed: " . $conn->error]);
        exit;
    }

    $stmt->bind_param("ssi", $guardian_name, $phone_number, $id);

    if ($stmt->execute()) {
        echo json_encode(["status" => "success", "message" => "Guardian updated successfully"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Update failed: " . $stmt->error]);
    }

    $stmt->close();
}
$conn->close();
