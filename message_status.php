<?php
header("Content-Type: application/json");
session_start();

try {
    $pdo = new PDO("mysql:host=127.0.0.1;dbname=smssystem;charset=utf8", "root", "10148570");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get message status summary
    $statusStmt = $pdo->prepare("
        SELECT 
            status,
            COUNT(*) as count,
            MAX(sent_at) as latest_sent
        FROM messages 
        WHERE sent_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY status
        ORDER BY count DESC
    ");
    $statusStmt->execute();
    $statusSummary = $statusStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get recent messages with details
    $recentStmt = $pdo->prepare("
        SELECT 
            m.msg_id,
            m.content,
            m.status,
            m.sent_at,
            g.guardian_name,
            g.phone_number,
            s.first_name,
            s.last_name
        FROM messages m
        JOIN guardians g ON m.recipient_id = g.id
        JOIN students s ON g.student_id = s.id
        WHERE m.sent_at >= DATE_SUB(NOW(), INTERVAL 2 DAY)
        ORDER BY m.sent_at DESC
        LIMIT 20
    ");
    $recentStmt->execute();
    $recentMessages = $recentStmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        "status" => "success",
        "status_summary" => $statusSummary,
        "recent_messages" => $recentMessages
    ]);
    
} catch (PDOException $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>