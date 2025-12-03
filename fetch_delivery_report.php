<?php
header("Content-Type: application/json");
session_start();

try {
    $pdo = new PDO("mysql:host=127.0.0.1;dbname=smssystem;charset=utf8", "root", "10148570");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get messages with guardian and student details - INCLUDING CAUSE
    $stmt = $pdo->prepare("
        SELECT 
            m.id,
            m.msg_id,
            m.content,
            m.status,
            m.cause,
            m.sent_at,
            m.grade_level,
            m.stream,
            m.boarding_status,
            g.guardian_name,
            g.phone_number,
            s.first_name as student_first_name,
            s.last_name as student_last_name,
            CONCAT(s.first_name, ' ', s.last_name) as student_name
        FROM messages m
        JOIN guardians g ON m.recipient_id = g.id
        JOIN students s ON g.student_id = s.id
        WHERE m.sent_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ORDER BY m.sent_at DESC
        LIMIT 200
    ");
    
    $stmt->execute();
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get status counts for summary
    $statusStmt = $pdo->prepare("
        SELECT 
            status,
            COUNT(*) as count,
            ROUND((COUNT(*) * 100.0 / (SELECT COUNT(*) FROM messages WHERE sent_at >= DATE_SUB(NOW(), INTERVAL 7 DAY))), 1) as percentage
        FROM messages 
        WHERE sent_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY status
    ");
    $statusStmt->execute();
    $statusSummary = $statusStmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        "status" => "success",
        "data" => [
            "messages" => $messages,
            "summary" => $statusSummary,
            "total_messages" => count($messages)
        ]
    ]);
    
} catch (PDOException $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>