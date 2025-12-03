<?php 
header("Content-Type: application/json");
session_start();

// Database connection with error handling
$servername = "127.0.0.1";
$username   = "root";
$password   = "10148570";
$dbname     = "smssystem"; 

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(["status" => "error", "message" => "Database connection failed: " . $e->getMessage()]);
    exit;
}

// Get logged-in sender_id
$sender_id = $_SESSION['user_id'] ?? 0;

// Read JSON input
$input = json_decode(file_get_contents("php://input"), true);

if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(["status" => "error", "message" => "Invalid JSON input"]);
    exit;
}

$grade_level     = $input['grade_level'] ?? null;
$stream          = $input['stream'] ?? null;
$boarding_status = $input['boarding_status'] ?? null;
$content         = $input['content'] ?? null;
$student_id      = $input['student_id'] ?? null; // For single messages
$phone_number    = $input['phone_number'] ?? null; // For single messages

// Check if this is a single message (has student_id and phone_number)
$is_single_message = ($student_id && $phone_number);

if ($is_single_message) {
    // SINGLE MESSAGE - Send to specific guardian only
    if (!$content) {
        echo json_encode(["status" => "error", "message" => "Missing message content."]);
        exit;
    }

    try {
        // Verify the student and guardian match
        $stmt = $pdo->prepare("
            SELECT g.id as guardian_id, s.grade_level, s.stream, s.boarding_status
            FROM guardians g
            JOIN students s ON g.student_id = s.id
            WHERE s.id = ? AND g.phone_number = ?
            LIMIT 1
        ");
        $stmt->execute([$student_id, $phone_number]);
        $recipient = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$recipient) {
            echo json_encode(["status" => "error", "message" => "Student/guardian not found or phone number mismatch."]);
            exit;
        }

        // Use the actual student details for the message record
        $grade_level = $recipient['grade_level'];
        $stream = $recipient['stream'];
        $boarding_status = $recipient['boarding_status'];
        $recipient_id = $recipient['guardian_id'];

        $recipients = [['id' => $recipient_id, 'phone_number' => $phone_number]];
        
    } catch (PDOException $e) {
        echo json_encode(["status" => "error", "message" => "Database error: " . $e->getMessage()]);
        exit;
    }

} else {
    // BULK MESSAGE - Original logic
    if (!$grade_level || !$stream || !$boarding_status || !$content) {
        echo json_encode(["status" => "error", "message" => "Missing required fields for bulk message."]);
        exit;
    }

    try {
        // Handle "all" options for bulk messaging
        $sql = "
            SELECT g.id, g.phone_number
            FROM guardians g
            JOIN students s ON g.student_id = s.id
            WHERE 1=1
        ";
        
        $params = [];
        
        if ($grade_level !== 'all') {
            $sql .= " AND s.grade_level = ?";
            $params[] = $grade_level;
        }
        
        if ($stream !== 'all') {
            $sql .= " AND s.stream = ?";
            $params[] = $stream;
        }
        
        if ($boarding_status !== 'all') {
            $sql .= " AND s.boarding_status = ?";
            $params[] = $boarding_status;
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!$recipients) {
            echo json_encode(["status" => "error", "message" => "No recipients found."]);
            exit;
        }
        
    } catch (PDOException $e) {
        echo json_encode(["status" => "error", "message" => "Database error: " . $e->getMessage()]);
        exit;
    }
}

// SMS API credentials
$userid   = "stann";
$password = "9SxaQd9m";
$senderid = "STANNSCH";
$apikey   = "c987dec898036dc85f411c08e6b53260ceda6b2d";

$sentCount = 0;
$failCount = 0;

// Prepare insert statement
try {
    $insertStmt = $pdo->prepare("
        INSERT INTO messages 
        (msg_id, content, sender_id, recipient_id, grade_level, stream, boarding_status, status, sent_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
} catch (PDOException $e) {
    echo json_encode(["status" => "error", "message" => "Database prepare error: " . $e->getMessage()]);
    exit;
}

foreach ($recipients as $recipient) {
    $recipient_id = $recipient['id'];
    $phone        = $recipient['phone_number'];

    $msgId = null;
    $status = "FAILED";
    $apiSuccess = false;

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
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $postData,
        CURLOPT_HTTPHEADER => [
            "apikey: $apikey",
            "content-type: application/x-www-form-urlencoded"
        ],
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error    = curl_error($ch);
    curl_close($ch);

    // Process API response to get msg_id
    if ($httpCode === 200 && !$error && $response) {
        $apiResponse = json_decode($response, true);
        
        if (json_last_error() === JSON_ERROR_NONE && $apiResponse) {
            // Check if the API returned success
            if (isset($apiResponse['response']['status']) && $apiResponse['response']['status'] === 'success') {
                // Extract msg_id from API response
                if (isset($apiResponse['response']['messageid'])) {
                    $msgId = $apiResponse['response']['messageid'];
                } elseif (isset($apiResponse['response']['messages'][0]['msgId'])) {
                    $msgId = $apiResponse['response']['messages'][0]['msgId'];
                } elseif (isset($apiResponse['msgId'])) {
                    $msgId = $apiResponse['msgId'];
                }
                
                if ($msgId) {
                    $status = "SUBMITTED";
                    $apiSuccess = true;
                    $sentCount++;
                } else {
                    $status = "NO_MSG_ID";
                    $failCount++;
                }
            } else {
                $status = "API_ERROR";
                $failCount++;
            }
        } else {
            $status = "JSON_ERROR";
            $failCount++;
        }
    } else {
        $status = "CURL_ERROR";
        $failCount++;
    }

    // Save to database with msg_id
    try {
        $insertStmt->execute([
            $msgId,           // msg_id (can be NULL if failed)
            $content, 
            $sender_id, 
            $recipient_id, 
            $grade_level, 
            $stream, 
            $boarding_status, 
            $status
        ]);
        
    } catch (PDOException $e) {
        $failCount++;
    }
}

echo json_encode([
    "status" => "success", 
    "message" => "Messages processed.",
    "sent" => $sentCount,
    "failed" => $failCount,
    "total_recipients" => count($recipients),
    "type" => $is_single_message ? "single" : "bulk"
]);
?>