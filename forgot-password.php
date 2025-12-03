<?php
session_start();

// Database connection
$servername = "127.0.0.1";
$username = "root";
$password = "10148570";
$dbname = "smssystem"; 

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $conn->real_escape_string($_POST['username']);
    $name = $conn->real_escape_string($_POST['name']);
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Check user exists
    $sql = "SELECT * FROM users WHERE username='$username' AND name ='$name' LIMIT 1";
    $result = $conn->query($sql);

    if ($result->num_rows == 1) {
        if ($new_password === $confirm_password) {
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $update = $conn->query("UPDATE users SET password='$hashed' WHERE username='$username'");

            if ($update) {
                echo "✅ Password has been reset successfully. <a href='login.html'>Login now</a>";
            } else {
                echo "❌ Failed to update password.";
            }
        } else {
            echo "❌ New passwords do not match.";
        }
    } else {
        echo "❌ User not found or name does not match.";
    }
}

$conn->close();
?>
