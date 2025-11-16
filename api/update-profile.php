<?php
// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: http://localhost');
    header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Max-Age: 86400');
    http_response_code(200);
    exit;
}

session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: http://localhost');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

require_once __DIR__ . '/../classes/User.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Not authenticated'
    ]);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

$full_name = isset($input['full_name']) ? trim($input['full_name']) : null;
$email = isset($input['email']) ? trim($input['email']) : null;
$phone = isset($input['phone']) ? trim($input['phone']) : null;
$address = isset($input['address']) ? trim($input['address']) : null;

// Validate email
if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid email address'
    ]);
    exit;
}

try {
    $user = new User();
    $result = $user->updateProfile($_SESSION['user_id'], $full_name, $email, $phone, $address);
    
    if ($result['success']) {
        // Update session email if changed
        if ($email) {
            $_SESSION['email'] = $email;
        }
        
        http_response_code(200);
        echo json_encode($result);
    } else {
        http_response_code(400);
        echo json_encode($result);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?>