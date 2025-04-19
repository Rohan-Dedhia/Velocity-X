<?php
$servername = "localhost";
$username = "root";  // Default XAMPP username
$password = "";      // Default XAMPP password is empty
$dbname = "test";    // Your database name

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get data from AJAX request
$order_id = $_POST['order_id'];
$rating = $_POST['rating'];
$review_text = $_POST['review_text'];

// Insert data into database
$sql = "INSERT INTO reviews (order_id, rating, review_text) VALUES ('$order_id', '$rating', '$review_text')";

if ($conn->query($sql) === TRUE) {
    echo json_encode(["status" => "success"]);
} else {
    echo json_encode(["status" => "error", "message" => $conn->error]);
}

$conn->close();
?>
