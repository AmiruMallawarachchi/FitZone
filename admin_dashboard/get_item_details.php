<?php
session_start();
require_once '../config.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('HTTP/1.1 403 Forbidden');
    exit('Access denied');
}

// Check if product_id is provided
if (!isset($_GET['id'])) {
    header('HTTP/1.1 400 Bad Request');
    exit('Product ID is required');
}

$product_id = intval($_GET['id']);

// Fetch item details
$stmt = $conn->prepare("SELECT * FROM products WHERE product_id = ?");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('HTTP/1.1 404 Not Found');
    exit('Product not found');
}

$item = $result->fetch_assoc();

// Return item details as JSON
header('Content-Type: application/json');
echo json_encode($item); 