<?php 
header("Content-Type: application/json");
session_start();

// Database connection
$servername = "127.0.0.1";
$username   = "root";
$password   = "10148570";
$dbname     = "smssystem"; 

$pdo = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8", $username, $password);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Get logged-in sender_id
$sender_id = $_SESSION['user_id'] ?? 0;

// Read JSON input
$input = json_decode(file_get_contents("php://input"), true);

$grade_level     = $input['grade_level'] ?? null;
$stream          = $input['stream'] ?? null;
$boarding_status = $input['boarding_status'] ?? null;
$content         = $input['content'] ?? null;

if (!$grade_level || !$stream || !$boarding_status || !$content) {
    echo json_encode(["status" => "error", "message" => "Missing required fields."]);
    exit;
}

// Get recipients
$stmt = $pdo->prepare("
    SELECT g.id, g.phone_number
    FROM guardians g
    JOIN students s ON g.student_id = s.id
    WHERE s.grade_level = ? AND s.stream = ? AND s.boarding_status = ?
");
$stmt->execute([$grade_level, $stream, $boarding_status]);
$recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$recipients) {
    echo json_encode(["status" => "error", "message" => "No recipients found."]);
    exit;
}

// SMS API credentials
$userid   = "stann";
$password = "9SxaQd9m";
$senderid = "STANNSCH";
$apikey   = "c987dec898036dc85f411c08e6b53260ceda6b2d";

$sentCount = 0;
$failCount = 0;
$dbSaveCount = 0;

// Prepare insert statement with msg_id
$insertStmt = $pdo->prepare("
    INSERT INTO messages 
    (msg_id, content, sender_id, recipient_id, grade_level, stream, boarding_status, status, sent_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
");

foreach ($recipients as $recipient) {
    $recipient_id = $recipient['id'];
    $phone        = $recipient['phone_number'];

    $msgId = null;
    $status = "FAILED";
    $apiResponse = null;

    // Prepare POST data for SMS API
    $postData = http_build_query([
        "userid"        => $userid,
        "password"      => $password,
        "mobile"        => $phone,
        "msg"           => $content,
        "senderid"      => $senderid,
        "msgType"       => "text",
        "duplicatecheck"=> "true",
        "output"        => "json",
        "sendMethod"    => "quick"
    ]);

    // Send SMS
    $ch = curl_init("https://smsportal.hostpinnacle.co.ke/SMSApi/send");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_ENCODING, "");
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "apikey: $apikey",
        "cache-control: no-cache",
        "content-type: application/x-www-form-urlencoded"
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error    = curl_error($ch);
    curl_close($ch);

    // Process API response to get msg_id
    if ($httpCode === 200 && !$error && $response) {
        $apiResponse = json_decode($response, true);
        
        if (json_last_error() === JSON_ERROR_NONE && $apiResponse) {
            // Extract msg_id from API response
            if (isset($apiResponse['response']['messages'][0]['msgId'])) {
                $msgId = $apiResponse['response']['messages'][0]['msgId'];
                $status = "SUBMITTED"; // Successfully submitted to SMS provider
                $sentCount++;
            } else {
                $status = "API_ERROR"; // API didn't return msgId
                $failCount++;
            }
        } else {
            $status = "JSON_ERROR"; // Invalid JSON response
            $failCount++;
        }
    } else {
        $status = "CURL_ERROR"; // cURL failed
        $failCount++;
    }

    // Save to database with msg_id
    try {
        $insertSuccess = $insertStmt->execute([
            $msgId,           // msg_id (can be NULL if failed)
            $content, 
            $sender_id, 
            $recipient_id, 
            $grade_level, 
            $stream, 
            $boarding_status, 
            $status
        ]);
        
        if ($insertSuccess) {
            $dbSaveCount++;
        }
    } catch (PDOException $e) {
        error_log("Database insert error: " . $e->getMessage());
        $failCount++;
    }
}

echo json_encode([
    "status" => "success", 
    "message" => "Messages processed with precise tracking.",
    "api_sent" => $sentCount,
    "failed" => $failCount,
    "database_saved" => $dbSaveCount,
    "total_recipients" => count($recipients)
]);
?>