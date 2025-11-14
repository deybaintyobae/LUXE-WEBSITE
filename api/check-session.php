<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'logged_in' => true,
        'user' => [
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'email' => $_SESSION['email']
        ]
    ]);
} else {
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'logged_in' => false
    ]);
}
?>