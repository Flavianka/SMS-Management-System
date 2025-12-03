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

// Get current counts before clearing
$beforeStats = $pdo->query("
    SELECT 
        COUNT(*) as total_messages,
        SUM(CASE WHEN msg_id IS NULL OR msg_id = '' THEN 1 ELSE 0 END) as messages_without_msg_id,
        SUM(CASE WHEN status IS NULL OR status = '' THEN 1 ELSE 0 END) as messages_without_status,
        SUM(CASE WHEN cause IS NULL OR cause = '' THEN 1 ELSE 0 END) as messages_without_cause,
        COUNT(DISTINCT msg_id) as unique_msg_ids
    FROM messages 
    WHERE sent_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
")->fetch(PDO::FETCH_ASSOC);

// Clear the fields for messages from the last 7 days
$clearStmt = $pdo->prepare("
    UPDATE messages 
    SET 
        msg_id = NULL,
        status = NULL,
        cause = NULL
    WHERE sent_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
");

$clearStmt->execute();
$affectedRows = $clearStmt->rowCount();

// Get counts after clearing
$afterStats = $pdo->query("
    SELECT 
        COUNT(*) as total_messages,
        SUM(CASE WHEN msg_id IS NULL OR msg_id = '' THEN 1 ELSE 0 END) as messages_without_msg_id,
        SUM(CASE WHEN status IS NULL OR status = '' THEN 1 ELSE 0 END) as messages_without_status,
        SUM(CASE WHEN cause IS NULL OR cause = '' THEN 1 ELSE 0 END) as messages_without_cause,
        COUNT(DISTINCT msg_id) as unique_msg_ids
    FROM messages 
    WHERE sent_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
")->fetch(PDO::FETCH_ASSOC);

// Get sample of cleared messages
$sampleMessages = $pdo->query("
    SELECT 
        id,
        msg_id,
        status, 
        cause,
        sent_at,
        LEFT(content, 50) as content_preview
    FROM messages 
    WHERE sent_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ORDER BY sent_at DESC 
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    "status" => "success",
    "summary" => [
        "affected_rows" => $affectedRows,
        "time_period" => "Last 7 days"
    ],
    "before_clearing" => $beforeStats,
    "after_clearing" => $afterStats,
    "sample_cleared_messages" => $sampleMessages,
    "next_steps" => [
        "1. Run this script to clear fields",
        "2. Run delivery_report.php to refill with correct data", 
        "3. Verify all messages now have correct msg_id, status, and cause"
    ]
]);
?>