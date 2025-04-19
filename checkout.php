<?php
// Set headers first to ensure proper JSON response
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, PUT');
header('Access-Control-Allow-Headers: Content-Type');

// Database configuration
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "test";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    http_response_code(500);
    die(json_encode([
        'status' => 'error',
        'message' => 'Database connection failed',
        'error' => $conn->connect_error
    ]));
}

// Get the raw POST data
$json = file_get_contents('php://input');
$data = json_decode($json, true);

// Validate JSON input
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    die(json_encode([
        'status' => 'error',
        'message' => 'Invalid JSON input',
        'error' => json_last_error_msg()
    ]));
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Validate required fields
        $required = ['full_name', 'street_address', 'city', 'postal_code', 'payment_method', 'total_amount'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                http_response_code(400);
                throw new Exception("Missing required field: $field");
            }
        }

        $conn->begin_transaction();

        // Escape inputs for safety
        $full_name = $conn->real_escape_string($data['full_name']);
        $street_address = $conn->real_escape_string($data['street_address']);
        $city = $conn->real_escape_string($data['city']);
        $postal_code = $conn->real_escape_string($data['postal_code']);
        $payment_method = $conn->real_escape_string($data['payment_method']);
        $upi_id = isset($data['upi_id']) ? $conn->real_escape_string($data['upi_id']) : 'NULL';
        $total_amount = (float)$data['total_amount']; // Typecast to float
        $payment_intent_id = 'NULL'; // Default to NULL

        // Construct and execute the query for orders
        $order_query = "INSERT INTO orders (
            full_name, street_address, city, postal_code, 
            payment_method, upi_id, total_amount, payment_intent_id
        ) VALUES (
            '$full_name', '$street_address', '$city', '$postal_code',
            '$payment_method', " . ($upi_id === 'NULL' ? 'NULL' : "'$upi_id'") . ", $total_amount, $payment_intent_id
        )";

        if (!$conn->query($order_query)) {
            throw new Exception("Order insertion failed: " . $conn->error);
        }

        $orderId = $conn->insert_id;

        // Insert order items
        if (empty($data['cartItems'])) {
            throw new Exception("Cart is empty");
        }

        foreach ($data['cartItems'] as $item) {
            if (empty($item['name']) || !isset($item['price'])) {
                continue; // Skip invalid items
            }

            $product_name = $conn->real_escape_string($item['name']);
            $product_price = (float)$item['price'];
            $quantity = isset($item['quantity']) ? (int)$item['quantity'] : 1;

            $item_query = "INSERT INTO order_items (
                order_id, product_name, product_price, quantity
            ) VALUES (
                $orderId, '$product_name', $product_price, $quantity
            )";

            if (!$conn->query($item_query)) {
                throw new Exception("Failed to add order item: " . $conn->error);
            }
        }

        $conn->commit();

        echo json_encode([
            'status' => 'success',
            'orderId' => $orderId,
            'message' => 'Order created successfully'
        ]);

    } elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
        // Update payment intent ID
        if (empty($data['order_id']) || empty($data['payment_intent_id'])) {
            http_response_code(400);
            throw new Exception("Missing order_id or payment_intent_id");
        }

        $order_id = (int)$data['order_id'];
        $payment_intent_id = $conn->real_escape_string($data['payment_intent_id']);

        $update_query = "UPDATE orders SET payment_intent_id = '$payment_intent_id' WHERE id = $order_id";

        if (!$conn->query($update_query)) {
            throw new Exception("Update failed: " . $conn->error);
        }

        echo json_encode([
            'status' => 'success',
            'message' => 'Order updated successfully'
        ]);
    } else {
        http_response_code(405);
        echo json_encode([
            'status' => 'error',
            'message' => 'Method not allowed'
        ]);
    }
} catch (Exception $e) {
    if ($conn->in_transaction) {
        $conn->rollback();
    }

    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
        'error' => $e->getFile() . ':' . $e->getLine()
    ]);
}

$conn->close();
?>