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

    // Login user
    public function login($username_or_email, $password) {
        try {
            $query = "SELECT * FROM {$this->table} 
                      WHERE (username = :identifier OR email = :identifier) 
                      AND is_active = 1 
                      LIMIT 1";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':identifier', $username_or_email);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                $row = $stmt->fetch();
                
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
            return $stmt->fetch();
        }
        
        return null;
    }
}
?>