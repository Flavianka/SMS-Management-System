<?php
// report.php
header('Content-Type: application/json; charset=utf-8');

// --- DB connection (mysqli) - update if needed ---
$servername = "127.0.0.1";
$username   = "root";
$password   = "10148570";
$dbname     = "smssystem";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "DB connection failed: " . $conn->connect_error]);
    exit;
}
$conn->set_charset('utf8mb4');

// ---- 1) Fetch guardians (with a phone value) and validate in PHP ----
$sql_guardians = "
    SELECT g.id AS guardian_id, g.guardian_name, g.phone_number,
           s.id AS student_id, s.first_name, s.last_name, s.grade_level, s.stream
    FROM guardians g
    JOIN students s ON s.id = g.student_id
    WHERE g.phone_number IS NOT NULL AND TRIM(g.phone_number) <> ''
";

$result = $conn->query($sql_guardians);
if ($result === false) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Query failed: " . $conn->error]);
    $conn->close();
    exit;
}

$invalid = [];

while ($row = $result->fetch_assoc()) {
    $phoneRaw = (string)$row['phone_number'];
    $phone = trim($phoneRaw);
    $errors = [];

    // 1) whitespace anywhere
    if (preg_match('/\s+/', $phone)) {
        $errors[] = "Contains whitespace";
    }

    // 2) asterisk(s) anywhere (one or many)
    if (preg_match('/\*+/', $phone)) {
        $errors[] = "Contains asterisk(s)";
    }

    // 3) underscore(s) anywhere
    if (preg_match('/_+/', $phone)) {
        $errors[] = "Contains underscore(s)";
    }

    // 4) multiple hyphens in a row (e.g. -- or ---)
    if (preg_match('/-{2,}/', $phone)) {
        $errors[] = "Contains multiple hyphens";
    }

    // 5) other invalid characters (letters or punctuation other than + () - space)
    // allow digits, plus, parentheses, hyphen and spaces — everything else flagged
    if (preg_match('/[^0-9\+\-\s\(\)]/', $phone)) {
        $errors[] = "Contains invalid characters";
    }

    // 6) count digits only (strip non-digit characters)
    $digitsOnly = preg_replace('/\D+/', '', $phone);
    $digitCount = strlen($digitsOnly);

    if ($digitCount < 10) {
        $errors[] = "Too few digits ({$digitCount})";
    } elseif ($digitCount > 12) {
        $errors[] = "Too many digits ({$digitCount})";
    }

    if (!empty($errors)) {
        $invalid[] = [
            "guardian_id" => $row['guardian_id'],
            "guardian_name" => $row['guardian_name'],
            "student_id" => $row['student_id'],
            "student_name" => trim($row['first_name'] . ' ' . $row['last_name']),
            "grade_level" => $row['grade_level'],
            "stream" => $row['stream'],
            "phone_number" => $phoneRaw,
            "issues" => $errors
        ];
    }
}
$result->free();

// ---- 2) Find students missing guardian phone (no guardians or guardians with empty phone) ----
// Approach: left join guardians where phone is present; if no such guardian row exists => missing
$sql_missing = "
    SELECT s.id AS student_id, s.first_name, s.last_name, s.grade_level, s.stream
    FROM students s
    LEFT JOIN guardians g 
      ON g.student_id = s.id 
      AND g.phone_number IS NOT NULL 
      AND TRIM(g.phone_number) <> ''
    WHERE g.id IS NULL
";

$result2 = $conn->query($sql_missing);
if ($result2 === false) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Query failed: " . $conn->error]);
    $conn->close();
    exit;
}

$missing = [];
while ($r = $result2->fetch_assoc()) {
    $missing[] = [
        "student_id" => $r['student_id'],
        "student_name" => trim($r['first_name'] . ' ' . $r['last_name']),
        "grade_level" => $r['grade_level'],
        "stream" => $r['stream']
    ];
}
$result2->free();

$conn->close();

// ---- Output ----
echo json_encode([
    "status" => "ok",
    "invalid" => $invalid,
    "missing" => $missing,
    "counts" => [
        "invalid_count" => count($invalid),
        "missing_count" => count($missing)
    ]
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
