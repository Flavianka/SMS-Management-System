<?php
// Database connection
$servername = "127.0.0.1";
$username = "root";
$password = "10148570";
$dbname = "smssystem";

$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $conn->real_escape_string($_POST['name']);
    $username = $conn->real_escape_string($_POST['username']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT); // hash password
    $role = $conn->real_escape_string($_POST['Role']);

    // Check if username already exists
    $checkUser = "SELECT * FROM users WHERE username='$username'";
    $result = $conn->query($checkUser);

    if ($result->num_rows > 0) {
        echo "❌ Username already taken. Please choose another.";
    } else {
        $sql = "INSERT INTO users (name, username, password, role) 
                VALUES ('$name', '$username', '$password', '$role')";

        if ($conn->query($sql) === TRUE) {
            echo "✅ Registration successful! <a href='login.html'>Login here</a>";
        } else {
            echo "Error: " . $sql . "<br>" . $conn->error;
        }
    }
}
$conn->close();
?>
