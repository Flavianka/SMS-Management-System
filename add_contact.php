<?php
// add_contact.php

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

// --- function to clean and normalize phone numbers ---
function normalizePhoneNumber($phone) {
    // remove all spaces
    $phone = preg_replace('/\s+/', '', $phone);

    // if it already starts with 254, return as is
    if (preg_match('/^254\d+$/', $phone)) {
        return $phone;
    }

    // if it starts with 07 or 01 -> remove leading 0 and prepend 254
    if (preg_match('/^0[71]\d+$/', $phone)) {
        return '254' . substr($phone, 1);
    }

    // if it starts with 7 or 1 -> just prepend 254
    if (preg_match('/^[71]\d+$/', $phone)) {
        return '254' . $phone;
    }

    // fallback - return unchanged
    return $phone;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id = $_POST['student_id'] ?? '';
    $guardian_name = $_POST['guardian_name'] ?? '';
    $phone_number = $_POST['phone_number'] ?? '';

    if (empty($student_id) || empty($guardian_name) || empty($phone_number)) {
        echo json_encode(["status" => "error", "message" => "All fields required"]);
        exit;
    }

    // normalize phone number before saving
    $phone_number = normalizePhoneNumber($phone_number);

    $stmt = $conn->prepare("INSERT INTO guardians (student_id, guardian_name, phone_number) VALUES (?, ?, ?)");
    if (!$stmt) {
        echo json_encode(["status" => "error", "message" => "Prepare failed: " . $conn->error]);
        exit;
    }

    $stmt->bind_param("iss", $student_id, $guardian_name, $phone_number);

    if ($stmt->execute()) {
        echo json_encode(["status" => "success", "message" => "Guardian added"]);
    } else {
        echo json_encode(["status" => "error", "message" => $stmt->error]);
    }
}
