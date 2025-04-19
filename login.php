<?php
session_start();
header("Content-Type: application/json");

// Database Connection
$conn = new mysqli("localhost", "root", "", "test"); // Updated database name to "test"

// Check if connection was successful
if ($conn->connect_error) {
    die(json_encode(["status" => "error", "message" => "Database connection failed"]));
}

// Get user input
$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';

// Prevent empty login attempts
if (empty($username) || empty($password)) {
    echo json_encode(["status" => "error", "message" => "Please enter both username and password"]);
    exit();
}

// Prepare SQL statement to fetch the user's hashed password
$sql = "SELECT id, username, password FROM users WHERE username = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

// Check if user exists
if ($result->num_rows > 0) {
    $user = $result->fetch_assoc(); // Fetch the user's data
    $hashed_password = $user['password']; // Get the hashed password from the database

    // Verify the input password against the hashed password
    if (password_verify($password, $hashed_password)) {
        // Password is correct, set session variables
        $_SESSION['user_id'] = $user['id']; // Store user ID in session
        $_SESSION['username'] = $user['username']; // Store username in session
        echo json_encode(["status" => "success"]);
    } else {
        // Password is incorrect
        echo json_encode(["status" => "error", "message" => "Invalid username or password"]);
    }
} else {
    // User does not exist
    echo json_encode(["status" => "error", "message" => "Invalid username or password"]);
}

// Close database connection
$stmt->close();
$conn->close();
?>