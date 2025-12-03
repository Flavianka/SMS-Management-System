<?php
function sanitizePhone($phone) {
    // 1. Remove whitespace
    $phone = preg_replace('/\s+/', '', $phone);

    // 2. Remove non-digit characters (optional, but safer)
    $phone = preg_replace('/[^0-9]/', '', $phone);

    // 3. Normalize to start with 254
    if (preg_match('/^0(7|1)/', $phone)) {
        // If it starts with 07... or 01..., remove the 0
        $phone = '254' . substr($phone, 1);
    } elseif (preg_match('/^(7|1)/', $phone)) {
        // If it starts with 7... or 1..., just prepend 254
        $phone = '254' . $phone;
    } elseif (!preg_match('/^254/', $phone)) {
        // If it doesn't already start with 254, just leave it as-is
        // or you could choose to reject it
    }

    return $phone;
}


session_start();
$servername = "127.0.0.1";
$username = "root";
$password = "10148570";
$dbname = "smssystem"; 

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die(json_encode(["status" => "error", "message" => $conn->connect_error]));
}

header('Content-Type: application/json');

$action = $_POST['action'] ?? 'fetch';

if ($action === "fetch") {
    // Fetch guardians
    $sql = "SELECT g.id, g.guardian_name, g.phone_number,
               CONCAT(s.first_name, ' ', s.last_name) AS student_name,
               s.grade_level, s.stream, s.boarding_status
              FROM guardians g
              INNER JOIN students s ON g.student_id = s.id
               WHERE 1=1";
    
    if (!empty($_POST['grade'])) {
      $grade = $conn->real_escape_string($_POST['grade']);
      $sql .= " AND s.grade_level = '$grade'";
    }

    if (!empty($_POST['stream'])) {
      $stream = $conn->real_escape_string($_POST['stream']);
      $sql .= " AND s.stream = '$stream'";
    }

    if (!empty($_POST['boarding'])) {
      $boarding = $conn->real_escape_string($_POST['boarding']);
      $sql .= " AND s.boarding_status = '$boarding'";
    }
    
    $result = $conn->query($sql);

    $guardians = [];
    while ($row = $result->fetch_assoc()) {
        $guardians[] = $row;
    }

    echo json_encode([
        "status" => "success",
        "data" => $guardians
    ], JSON_PRETTY_PRINT);
}

elseif ($action === "add") {
    $guardian_name = $_POST['guardian_name'] ?? '';
    $phone_number = $_POST['phone_number'] ?? '';
    $student_id = $_POST['student_id'] ?? '';

    if (empty($guardian_name) || empty($phone_number) || empty($student_id)) {
        echo json_encode(["status" => "error", "message" => "All fields required"]);
        exit;
    }

    // Sanitize phone number
    $phone_number = sanitizePhone($phone_number);

    $stmt = $conn->prepare("INSERT INTO guardians (guardian_name, phone_number, student_id) VALUES (?, ?, ?)");
    $stmt->bind_param("ssi", $guardian_name, $phone_number, $student_id);

    if ($stmt->execute()) {
        echo json_encode(["status" => "success", "message" => "Guardian added successfully"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Add failed: " . $stmt->error]);
    }

    $stmt->close();
}


elseif ($action === "edit") {
    $id = $_POST['id'] ?? '';
    $guardian_name = $_POST['guardian_name'] ?? '';
    $phone_number = $_POST['phone_number'] ?? '';

    if (empty($id) || empty($guardian_name) || empty($phone_number)) {
        echo json_encode(["status" => "error", "message" => "All fields required"]);
        exit;
    }

    // Sanitize phone number
    $phone_number = sanitizePhone($phone_number);

    $stmt = $conn->prepare("UPDATE guardians SET guardian_name = ?, phone_number = ? WHERE id = ?");
    $stmt->bind_param("ssi", $guardian_name, $phone_number, $id);

    if ($stmt->execute()) {
        echo json_encode(["status" => "success", "message" => "Guardian updated successfully"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Update failed: " . $stmt->error]);
    }

    $stmt->close();
}


elseif ($action === "delete") {
    $id = $_POST['id'] ?? '';
    if (empty($id)) {
        echo json_encode(["status" => "error", "message" => "ID required"]);
        exit;
    }

    $stmt = $conn->prepare("DELETE FROM guardians WHERE id = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        echo json_encode(["status" => "success", "message" => "Guardian deleted successfully"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Delete failed: " . $stmt->error]);
    }

    $stmt->close();
}

$conn->close();
?>
