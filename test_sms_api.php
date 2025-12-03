<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Simple SMS API Test
$userid = "stann";
$password = "9SxaQd9m";
$fromDate = $_POST['fromDate'] ?? date("Y-m-d", strtotime("-3 days"));
$toDate = $_POST['toDate'] ?? date("Y-m-d");
$pageLimit = $_POST['pageLimit'] ?? 999;

$url = "https://smsportal.hostpinnacle.co.ke/SMSApi/reports/status?userid=$userid&password=$password&method=getDlr&fromDate=$fromDate&toDate=$toDate&pageLimit=$pageLimit&output=json";

echo "<h1>Simple SMS API Test</h1>";
echo "<p><strong>URL:</strong> " . htmlspecialchars($url) . "</p>";

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_TIMEOUT => 30,
]);

$response = curl_exec($ch);
$error = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($error) {
    echo "<h2 style='color: red;'>Error:</h2>";
    echo "<pre>" . htmlspecialchars($error) . "</pre>";
} else {
    echo "<h2>HTTP Code: $httpCode</h2>";
    echo "<h2>Raw Response:</h2>";
    echo "<pre>" . htmlspecialchars($response) . "</pre>";
    
    // Try to decode JSON to see structure
    $data = json_decode($response, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        echo "<h2>JSON Structure:</h2>";
        echo "<pre>" . print_r($data, true) . "</pre>";
    } else {
        echo "<h2>JSON Decode Error:</h2>";
        echo "<p>" . json_last_error_msg() . "</p>";
    }
}
?>

<form method="POST" style="margin-top: 20px; padding: 20px; border: 1px solid #ccc;">
    <h3>Test Different Dates:</h3>
    From: <input type="date" name="fromDate" value="<?php echo $fromDate; ?>">
    To: <input type="date" name="toDate" value="<?php echo $toDate; ?>">
    Limit: <input type="number" name="pageLimit" value="<?php echo $pageLimit; ?>" style="width: 80px;">
    <input type="submit" value="Test API">
</form>