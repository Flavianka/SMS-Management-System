<?php
// Infobip API credentials
$apiKey = "290c8650de8180fbf15f3526082c43f1-d89c3713-deed-45dd-b4df-2a715e0d7b66";   // Replace with your Infobip API key
$baseUrl = "http://lq5gw2.api.infobip.com"; // Replace with your Infobip base URL

// SMS details
$sender = "SchoolSys";  // Sender ID (must be approved by Infobip)
$recipients = [
    "+254705964700"  // Kenya number    
];
$messageText = "Hello! This is a test bulk SMS from PHP.";

// Prepare request payload
$data = [
    "messages" => [
        [
            "from" => $sender,
            "destinations" => array_map(function ($phone) {
                return ["to" => $phone];
            }, $recipients),
            "text" => $messageText
        ]
    ]
];

// Initialize cURL
$ch = curl_init("$baseUrl/sms/2/text/advanced");
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: App $apiKey",
    "Content-Type: application/json",
    "Accept: application/json"
]);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

// Execute request
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Handle response
if ($httpCode == 200) {
    echo "✅ SMS sent successfully: " . $response;
} else {
    echo "❌ Failed to send SMS. HTTP Status: $httpCode. Response: " . $response;
}
?>
