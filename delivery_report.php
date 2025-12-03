<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST");
header("Access-Control-Allow-Headers: Content-Type");

try {
    $pdo = new PDO("mysql:host=127.0.0.1;dbname=smssystem;charset=utf8", "root", "10148570");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(["error" => "DB connection failed", "details" => $e->getMessage()]);
    exit;
}

$userid = "stann";
$password = "9SxaQd9m";

// Get date range from messages needing updates
$dateRangeStmt = $pdo->query("
    SELECT 
        DATE(MIN(sent_at)) as earliest_date,
        DATE(MAX(sent_at)) as latest_date
    FROM messages 
    WHERE sent_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    AND (msg_id IS NULL OR msg_id = '' OR status IN ('PENDING', 'SENT', 'SUBMITTED', 'API_ERROR'))
");
$dateRange = $dateRangeStmt->fetch(PDO::FETCH_ASSOC);

if ($dateRange['earliest_date']) {
    $fromDate = $dateRange['earliest_date'];
    $toDate = $dateRange['latest_date'];
} else {
    $fromDate = date("Y-m-d", strtotime("-3 days"));
    $toDate = date("Y-m-d");
}

$url = "https://smsportal.hostpinnacle.co.ke/SMSApi/reports/status?userid=$userid&password=$password&method=getDlr&fromDate=$fromDate&toDate=$toDate&pageLimit=500&output=json";
$url .= "&_=" . time();

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_TIMEOUT => 30,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    echo json_encode(["error" => "cURL Error", "details" => $error]);
    exit;
}

if ($httpCode !== 200) {
    echo json_encode(["error" => "HTTP Error", "details" => "HTTP Code: " . $httpCode]);
    exit;
}

$data = json_decode($response, true);

if (!isset($data['response']['reports_dlrList'])) {
    echo json_encode(["error" => "Invalid API response structure"]);
    exit;
}

$reports = $data['response']['reports_dlrList'];
$updated = 0;
$matching_details = [];

// Sort API reports by submitTime (OLDEST FIRST)
usort($reports, function($a, $b) {
    $timeA = $a['submitTime'] ?? 0;
    $timeB = $b['submitTime'] ?? 0;
    
    // Convert to timestamp for comparison
    if (is_numeric($timeA) && strlen($timeA) === 13) {
        $timeA = intval($timeA) / 1000;
    } elseif (is_numeric($timeA) && strlen($timeA) === 10) {
        $timeA = intval($timeA);
    } else {
        $timeA = strtotime($timeA) ?: 0;
    }
    
    if (is_numeric($timeB) && strlen($timeB) === 13) {
        $timeB = intval($timeB) / 1000;
    } elseif (is_numeric($timeB) && strlen($timeB) === 10) {
        $timeB = intval($timeB);
    } else {
        $timeB = strtotime($timeB) ?: 0;
    }
    
    return $timeA - $timeB;
});

// Get messages needing updates, ordered by sent_at (OLDEST FIRST)
$messagesNeedingUpdate = $pdo->query("
    SELECT 
        m.id,
        m.msg_id,
        m.status, 
        m.content,
        m.sent_at,
        DATE(m.sent_at) as sent_date,
        g.phone_number,
        m.cause
    FROM messages m 
    JOIN guardians g ON m.recipient_id = g.id 
    WHERE m.sent_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    AND (m.msg_id IS NULL OR m.msg_id = '' OR m.status IN ('PENDING', 'SENT', 'SUBMITTED', 'API_ERROR'))
    ORDER BY m.sent_at ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Track used msgIds to prevent duplicates
$usedMsgIds = [];

foreach ($reports as $reportIndex => $report) {
    $msgId = $report['msgId'] ?? null;
    $status = strtoupper($report['status'] ?? 'UNKNOWN');
    $mobileNo = $report['mobileNo'] ?? '';
    $text = $report['text'] ?? '';
    $submitTime = $report['submitTime'] ?? null;
    $cause = $report['cause'] ?? '';

    if (!$msgId || !$mobileNo) continue;

    // Skip if msgId already used in this batch
    if (in_array($msgId, $usedMsgIds)) {
        $matching_details[] = [
            'report_index' => $reportIndex,
            'report_msg_id' => $msgId,
            'status' => 'SKIPPED',
            'reason' => 'msgId_already_used_in_this_batch',
            'phone' => normalizePhoneNumber($mobileNo)
        ];
        continue;
    }

    $matched = false;
    $match_method = '';
    $cleanText = $text ? trim(str_replace('STOP *456*9*5#', '', $text)) : '';
    $normalizedPhone = normalizePhoneNumber($mobileNo);

    // Convert submitTime to get date only
    $submitDate = null;
    if ($submitTime) {
        if (is_numeric($submitTime) && strlen($submitTime) === 13) {
            $timestamp = intval($submitTime) / 1000;
            $submitDate = date('Y-m-d', $timestamp);
        } elseif (is_numeric($submitTime) && strlen($submitTime) === 10) {
            $submitDate = date('Y-m-d', intval($submitTime));
        } else {
            $timestamp = strtotime($submitTime);
            if ($timestamp) $submitDate = date('Y-m-d', $timestamp);
        }
    }

    // Find the NEXT available matching message (top-to-bottom approach)
    foreach ($messagesNeedingUpdate as $dbIndex => $dbMessage) {
        $dbPhone = $dbMessage['phone_number'];
        $dbContent = $dbMessage['content'];
        $dbSentDate = $dbMessage['sent_date'];
        $dbId = $dbMessage['id'];
        $dbMsgId = $dbMessage['msg_id'];
        
        $dbCleanContent = trim(str_replace('STOP *456*9*5#', '', $dbContent));
        
        // STRICT COMPOSITE MATCHING CRITERIA:
        $phoneMatch = ($dbPhone === $normalizedPhone);
        $contentMatch = ($cleanText === $dbCleanContent);
        $dateMatch = ($submitDate && $submitDate === $dbSentDate); // MUST BE SAME DATE
        
        // CRITICAL: Only match if ALL THREE conditions are met
        if ($phoneMatch && $contentMatch && $dateMatch) {
            // Found a match! Update this message
            $stmt = $pdo->prepare("UPDATE messages SET status = ?, msg_id = ?, cause = ? WHERE id = ?");
            $stmt->execute([$status, $msgId, $cause, $dbId]);
            
            if ($stmt->rowCount() > 0) {
                $updated += $stmt->rowCount();
                $matched = true;
                $match_method = 'top_to_bottom_same_date';
                $usedMsgIds[] = $msgId;
                
                // Remove this message from further consideration
                unset($messagesNeedingUpdate[$dbIndex]);
                break; // Move to next API report
            }
        }
    }

    // Record matching details
    $matching_details[] = [
        'report_index' => $reportIndex,
        'report_msg_id' => $msgId,
        'report_status' => $status,
        'report_cause' => $cause,
        'report_mobile' => $mobileNo,
        'normalized_mobile' => $normalizedPhone,
        'report_text_preview' => substr($cleanText, 0, 30),
        'submit_date' => $submitDate,
        'matched' => $matched,
        'match_method' => $match_method,
        'remaining_db_messages' => count($messagesNeedingUpdate)
    ];
}

// Get final stats
$finalStats = $pdo->query("
    SELECT 
        COUNT(*) as total_messages,
        SUM(CASE WHEN msg_id IS NULL OR msg_id = '' THEN 1 ELSE 0 END) as messages_without_msg_id,
        SUM(CASE WHEN status IN ('PENDING', 'SENT', 'SUBMITTED', 'API_ERROR') THEN 1 ELSE 0 END) as messages_needing_update,
        COUNT(DISTINCT msg_id) as unique_msg_ids
    FROM messages 
    WHERE sent_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
")->fetch(PDO::FETCH_ASSOC);

// Output ONLY JSON (no debug text)
echo json_encode([
    "status" => "success",
    "summary" => [
        "total_reports_received" => count($reports),
        "successfully_updated" => $updated,
        "matching_rate" => count($reports) > 0 ? round(($updated / count($reports)) * 100, 2) . '%' : '0%',
        "remaining_db_messages" => count($messagesNeedingUpdate),
        "unique_msgIds_used" => count($usedMsgIds)
    ],
    "final_database_stats" => $finalStats,
    "matching_details_sample" => array_slice($matching_details, 0, 10),
    "matching_strategy" => [
        "approach" => "STRICT TOP-TO-BOTTOM WITH DATE VALIDATION",
        "critical_rule" => "MUST HAVE SAME DATE (API submitDate = DB sent_date)",
        "sorting" => [
            "API Reports: Oldest submitTime first",
            "DB Messages: Oldest sent_at first"
        ],
        "matching_criteria" => [
            "1. Exact phone number match",
            "2. Exact message content match (excluding STOP suffix)", 
            "3. EXACT SAME DATE (no cross-day matching)"
        ]
    ]
]);

/**
 * Normalize phone number to 254 format
 */
function normalizePhoneNumber($phone) {
    $clean = preg_replace('/[^0-9]/', '', $phone);
    
    if (strlen($clean) === 9 && $clean[0] === '7') {
        return '254' . $clean;
    } elseif (strlen($clean) === 10 && $clean[0] === '0') {
        return '254' . substr($clean, 1);
    } elseif (strlen($clean) === 12 && substr($clean, 0, 3) === '254') {
        return $clean;
    }
    
    return $clean;
}
?>