<?php
require_once __DIR__ . '/../config/database.php';

class User {
    private $conn;
    private $table = 'users';

    public $id;
    public $username;
    public $email;
    public $password;
    public $full_name;
    public $created_at;
    public $last_login;
    public $is_active;

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    // Register new user
    public function register($username, $email, $password, $full_name = null) {
        try {
            // Check if username or email already exists
            if ($this->userExists($username, $email)) {
                return [
                    'success' => false,
                    'message' => 'Username or email already exists'
                ];
            }

            // Validate input
            if (!$this->validateInput($username, $email, $password)) {
                return [
                    'success' => false,
                    'message' => 'Invalid input data'
                ];
            }

            // Hash password
            $hashed_password = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

            // Insert user
            $query = "INSERT INTO {$this->table} (username, email, password, full_name) 
                        VALUES (:username, :email, :password, :full_name)";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':username', $username);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':password', $hashed_password);
            $stmt->bindParam(':full_name', $full_name);

            if ($stmt->execute()) {
                return [
                    'success' => true,
                    'message' => 'Registration successful',
                    'user_id' => $this->conn->lastInsertId()
                ];
            }

            return [
                'success' => false,
                'message' => 'Registration failed'
            ];

        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => 'Database error: ' . $e->getMessage()
            ];
        }
    }

    // Login user - FIXED VERSION
    public function login($username_or_email, $password) {
        try {
            // FIXED: Use two separate parameters for username and email
            $query = "SELECT * FROM {$this->table} 
                      WHERE (username = :username OR email = :email) 
                      AND is_active = 1 
                      LIMIT 1";
            
            $stmt = $this->conn->prepare($query);
            
            // Bind both parameters with the same value
            $stmt->bindParam(':username', $username_or_email);
            $stmt->bindParam(':email', $username_or_email);
            
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Verify password
                if (password_verify($password, $row['password'])) {
                    // Update last login
                    $this->updateLastLogin($row['id']);
                    
                    // Remove password from returned data
                    unset($row['password']);
                    
                    return [
                        'success' => true,
                        'message' => 'Login successful',
                        'user' => $row
                    ];
                }
            }

            return [
                'success' => false,
                'message' => 'Invalid username/email or password'
            ];

        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => 'Database error: ' . $e->getMessage()
            ];
        }
    }

    // Check if user exists
    private function userExists($username, $email) {
        $query = "SELECT id FROM {$this->table} 
                  WHERE username = :username OR email = :email 
                  LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':email', $email);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    // Validate input
    private function validateInput($username, $email, $password) {
        // Username validation
        if (strlen($username) < 3 || strlen($username) > 50) {
            return false;
        }
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            return false;
        }

        // Email validation
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        // Password validation
        if (strlen($password) < 8) {
            return false;
        }

        return true;
    }

    // Update last login
    private function updateLastLogin($user_id) {
        $query = "UPDATE {$this->table} SET last_login = CURRENT_TIMESTAMP WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $user_id);
        $stmt->execute();
    }

    // Get user by ID
    public function getUserById($user_id) {
        $query = "SELECT id, username, email, full_name, created_at, last_login 
                  FROM {$this->table} WHERE id = :id AND is_active = 1 LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $user_id);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        return null;
    }
    
    // Update user profile
    public function updateProfile($user_id, $full_name = null, $email = null) {
        try {
            // Check if email is already taken by another user
            if ($email) {
                $query = "SELECT id FROM {$this->table} 
                          WHERE email = :email AND id != :user_id LIMIT 1";
                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(':email', $email);
                $stmt->bindParam(':user_id', $user_id);
                $stmt->execute();
                
                if ($stmt->rowCount() > 0) {
                    return [
                        'success' => false,
                        'message' => 'Email is already in use by another account'
                    ];
                }
            }
            
            // Build update query
            $updates = [];
            $params = [':user_id' => $user_id];
            
            if ($full_name !== null) {
                $updates[] = "full_name = :full_name";
                $params[':full_name'] = $full_name;
            }
            
            if ($email !== null) {
                $updates[] = "email = :email";
                $params[':email'] = $email;
            }
            
            if (empty($updates)) {
                return [
                    'success' => false,
                    'message' => 'No fields to update'
                ];
            }
            
            $query = "UPDATE {$this->table} SET " . implode(', ', $updates) . " WHERE id = :user_id";
            $stmt = $this->conn->prepare($query);
            
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            
            if ($stmt->execute()) {
                return [
                    'success' => true,
                    'message' => 'Profile updated successfully'
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Failed to update profile'
            ];
            
        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => 'Database error: ' . $e->getMessage()
            ];
        }
    }
    
    // Change password
    public function changePassword($user_id, $current_password, $new_password) {
        try {
            // Get current password hash
            $query = "SELECT password FROM {$this->table} WHERE id = :id LIMIT 1";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $user_id);
            $stmt->execute();
            
            if ($stmt->rowCount() === 0) {
                return [
                    'success' => false,
                    'message' => 'User not found'
                ];
            }
            
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Verify current password
            if (!password_verify($current_password, $row['password'])) {
                return [
                    'success' => false,
                    'message' => 'Current password is incorrect'
                ];
            }
            
            // Hash new password
            $hashed_password = password_hash($new_password, PASSWORD_BCRYPT, ['cost' => 12]);
            
            // Update password
            $query = "UPDATE {$this->table} SET password = :password WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':password', $hashed_password);
            $stmt->bindParam(':id', $user_id);
            
            if ($stmt->execute()) {
                return [
                    'success' => true,
                    'message' => 'Password changed successfully'
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Failed to change password'
            ];
            
        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => 'Database error: ' . $e->getMessage()
            ];
        }
    }
}
?>