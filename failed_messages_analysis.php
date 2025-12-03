<?php
header("Content-Type: application/json");
session_start();

try {
    $pdo = new PDO("mysql:host=127.0.0.1;dbname=smssystem;charset=utf8", "root", "10148570");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get failed messages with guardian details
    $stmt = $pdo->prepare("
        SELECT 
            m.id,
            m.msg_id,
            m.status,
            m.content,
            m.sent_at,
            g.guardian_name,
            g.phone_number,
            s.first_name,
            s.last_name,
            s.grade_level,
            s.stream
        FROM messages m
        JOIN guardians g ON m.recipient_id = g.id
        JOIN students s ON g.student_id = s.id
        WHERE m.status IN ('FAILED', 'API_ERROR', 'CURL_ERROR', 'NOT SENT')
        AND m.sent_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ORDER BY m.sent_at DESC
        LIMIT 100
    ");
    
    $stmt->execute();
    $failedMessages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Analyze phone number patterns
    $phoneAnalysis = [];
    foreach ($failedMessages as $message) {
        $phone = $message['phone_number'];
        $analysis = analyzePhoneNumber($phone);
        $phoneAnalysis[] = [
            'phone' => $phone,
            'guardian' => $message['guardian_name'],
            'student' => $message['first_name'] . ' ' . $message['last_name'],
            'analysis' => $analysis,
            'issue' => $analysis['is_valid'] ? 'Route/Network Issue' : 'Invalid Format'
        ];
    }
    
    // Get network distribution
    $networkDistribution = [];
    foreach ($phoneAnalysis as $item) {
        $network = $item['analysis']['network'];
        $networkDistribution[$network] = ($networkDistribution[$network] ?? 0) + 1;
    }
    
    echo json_encode([
        "status" => "success",
        "summary" => [
            "total_failed_messages" => count($failedMessages),
            "invalid_format_count" => count(array_filter($phoneAnalysis, fn($item) => !$item['analysis']['is_valid'])),
            "network_distribution" => $networkDistribution
        ],
        "failed_messages" => array_slice($failedMessages, 0, 20),
        "phone_analysis" => $phoneAnalysis,
        "common_issues" => getCommonIssuesSummary($phoneAnalysis),
        "network_info" => getNetworkPrefixInfo()
    ]);
    
} catch (PDOException $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}

function analyzePhoneNumber($phone) {
    $analysis = [
        'original' => $phone,
        'is_valid' => false,
        'format_issues' => [],
        'suggested_format' => '',
        'network' => 'Unknown',
        'prefix' => ''
    ];
    
    // Remove any spaces, dashes, or other separators
    $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
    $analysis['cleaned'] = $cleanPhone;
    
    // Extract prefix for analysis
    if (preg_match('/^(\+?254|0|7)([0-9]+)$/', $cleanPhone, $matches)) {
        $analysis['prefix'] = extractPrefix($cleanPhone);
    }
    
    // Check length
    if (strlen($cleanPhone) < 9) {
        $analysis['format_issues'][] = "Too short (less than 9 digits)";
    }
    
    if (strlen($cleanPhone) > 13) {
        $analysis['format_issues'][] = "Too long (more than 13 digits)";
    }
    
    // Check for Kenya number format
    if (preg_match('/^2547[0-9]{8}$/', $cleanPhone)) {
        $analysis['is_valid'] = true;
        $analysis['suggested_format'] = $cleanPhone;
        $analysis['network'] = detectKenyanNetwork($cleanPhone);
    } 
    // Check if it starts with 07 and needs 254 conversion
    elseif (preg_match('/^07[0-9]{8}$/', $cleanPhone)) {
        $analysis['is_valid'] = true;
        $analysis['suggested_format'] = '254' . substr($cleanPhone, 1);
        $analysis['format_issues'][] = "Should use international format (254 instead of 0)";
        $analysis['network'] = detectKenyanNetwork($analysis['suggested_format']);
    }
    // Check if it starts with 7 (missing country code)
    elseif (preg_match('/^7[0-9]{8}$/', $cleanPhone)) {
        $analysis['is_valid'] = true;
        $analysis['suggested_format'] = '254' . $cleanPhone;
        $analysis['format_issues'][] = "Missing country code";
        $analysis['network'] = detectKenyanNetwork($analysis['suggested_format']);
    }
    // Check if it starts with +254
    elseif (preg_match('/^\+2547[0-9]{8}$/', $cleanPhone)) {
        $analysis['is_valid'] = true;
        $analysis['suggested_format'] = substr($cleanPhone, 1); // Remove +
        $analysis['format_issues'][] = "Remove '+' symbol";
        $analysis['network'] = detectKenyanNetwork($analysis['suggested_format']);
    }
    else {
        $analysis['format_issues'][] = "Invalid Kenyan mobile number format";
    }
    
    return $analysis;
}

function extractPrefix($phone) {
    $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
    
    // Convert to 254 format for consistent prefix extraction
    if (preg_match('/^0([0-9]{9})$/', $cleanPhone, $matches)) {
        $cleanPhone = '254' . $matches[1];
    } elseif (preg_match('/^7([0-9]{8})$/', $cleanPhone)) {
        $cleanPhone = '254' . $cleanPhone;
    } elseif (preg_match('/^\+254([0-9]{9})$/', $cleanPhone, $matches)) {
        $cleanPhone = '254' . $matches[1];
    }
    
    if (preg_match('/^254([0-9]{3})/', $cleanPhone, $matches)) {
        return $matches[1]; // Returns first 3 digits after 254
    }
    
    return 'Unknown';
}

function detectKenyanNetwork($phone) {
    $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
    $prefix = extractPrefix($cleanPhone);
    
    // Safaricom prefixes
    $safaricomPrefixes = ['70', '71', '72', '79'];
    foreach ($safaricomPrefixes as $safPrefix) {
        if (strpos($prefix, $safPrefix) === 0) {
            return 'Safaricom';
        }
    }
    
    // Airtel prefixes
    $airtelPrefixes = ['73', '75', '76', '100', '101', '102', '107', '108'];
    foreach ($airtelPrefixes as $airtelPrefix) {
        if (strpos($prefix, $airtelPrefix) === 0) {
            return 'Airtel';
        }
    }
    
    // Telkom prefixes (0770-0779)
    $telkomPrefixes = ['770', '771', '772', '773', '774', '775', '776', '777', '778', '779'];
    foreach ($telkomPrefixes as $telkomPrefix) {
        if ($prefix === $telkomPrefix) {
            return 'Telkom';
        }
    }
    
    // Faiba prefix (0747)
    if ($prefix === '747') {
        return 'Faiba';
    }
    
    return 'Unknown Network';
}

function getCommonIssuesSummary($analysis) {
    $issues = [];
    
    foreach ($analysis as $item) {
        if (!empty($item['analysis']['format_issues'])) {
            foreach ($item['analysis']['format_issues'] as $issue) {
                $issues[$issue] = ($issues[$issue] ?? 0) + 1;
            }
        }
    }
    
    return $issues;
}

function getNetworkPrefixInfo() {
    return [
        'Safaricom' => [
            'prefixes' => ['70x', '71x', '72x', '79x'],
            'examples' => ['254701...', '254711...', '254721...', '254791...'],
            'coverage' => 'Nationwide'
        ],
        'Airtel' => [
            'prefixes' => ['73x', '75x', '76x', '100', '101', '102', '107', '108'],
            'examples' => ['254730...', '254750...', '254760...', '254100...'],
            'coverage' => 'Nationwide'
        ],
        'Telkom' => [
            'prefixes' => ['770', '771', '772', '773', '774', '775', '776', '777', '778', '779'],
            'examples' => ['254770...', '254771...', '254776...'],
            'coverage' => 'Major urban areas'
        ],
        'Faiba' => [
            'prefixes' => ['747'],
            'examples' => ['254747...'],
            'coverage' => 'Nairobi and major towns'
        ]
    ];
}
?>