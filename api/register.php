<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../classes/User.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Validate required fields
$required_fields = ['username', 'email', 'password'];
foreach ($required_fields as $field) {
    if (!isset($input[$field]) || empty(trim($input[$field]))) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => ucfirst($field) . ' is required'
        ]);
        exit;
    }
}

// Sanitize inputs
$username = trim($input['username']);
$email = trim($input['email']);
$password = $input['password'];
$full_name = isset($input['full_name']) ? trim($input['full_name']) : null;

// Confirm password check
if (isset($input['confirm_password']) && $input['password'] !== $input['confirm_password']) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Passwords do not match'
    ]);
    exit;
}

// Create User instance and register
$user = new User();
$result = $user->register($username, $email, $password, $full_name);

if ($result['success']) {
    http_response_code(201);
    echo json_encode($result);
} else {
    http_response_code(400);
    echo json_encode($result);
}
?>