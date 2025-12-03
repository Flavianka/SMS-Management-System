<?php
// Your Infobip API credentials
$apiKey = "7cef587972f279a57a5daf629406c30d-293cdf68-2bad-4d02-97ce-a9af7cc672e1";
$baseUrl = "https://api.infobip.com";

// SMS details
$from = "447491163443";  // Sender ID (from Infobip setup)
$to = "254705964700";    // Replace with your test number
$message = "Congratulations on sending your first message. Go ahead and check the delivery report in the next step.";

// Prepare request payload
$data = [
    "messages" => [
        [
            "from" => $from,
            "destinations" => [
                ["to" => $to]
            ],
            "text" => $message
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
$error = curl_error($ch);
curl_close($ch);

// Handle response
if ($error) {
    echo "cURL Error: " . $error;
} elseif ($httpCode == 200) {
    echo "✅ SMS sent successfully! Response: " . $response;
} else {
    echo "❌ Failed to send SMS. HTTP Status: $httpCode. Response: " . $response;
}
