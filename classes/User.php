<?php
require_once __DIR__ . '/../config/database.php';

class User {
    private $conn;
    private $table = 'users';
    private $reset_table = 'password_resets';

    public $id;
    public $username;
    public $email;
    public $password;
    public $full_name;
    public $phone;
    public $address;
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
            if ($this->userExists($username, $email)) {
                return [
                    'success' => false,
                    'message' => 'Username or email already exists'
                ];
            }

            if (!$this->validateInput($username, $email, $password)) {
                return [
                    'success' => false,
                    'message' => 'Invalid input data'
                ];
            }

            $hashed_password = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

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
                      WHERE (username = :username OR email = :email) 
                      AND is_active = 1 
                      LIMIT 1";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':username', $username_or_email);
            $stmt->bindParam(':email', $username_or_email);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (password_verify($password, $row['password'])) {
                    $this->updateLastLogin($row['id']);
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
        if (strlen($username) < 3 || strlen($username) > 50) {
            return false;
        }
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            return false;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
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
        $query = "SELECT id, username, email, full_name, phone, address, created_at, last_login 
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
    public function updateProfile($user_id, $full_name = null, $email = null, $phone = null, $address = null) {
        try {
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
            
            if ($phone !== null) {
                $updates[] = "phone = :phone";
                $params[':phone'] = $phone;
            }
            
            if ($address !== null) {
                $updates[] = "address = :address";
                $params[':address'] = $address;
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
            
            if (!password_verify($current_password, $row['password'])) {
                return [
                    'success' => false,
                    'message' => 'Current password is incorrect'
                ];
            }
            
            $hashed_password = password_hash($new_password, PASSWORD_BCRYPT, ['cost' => 12]);
            
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
    
    // Forgot password
    public function forgotPassword($email) {
        try {
            $query = "SELECT id, username, email FROM {$this->table} 
                      WHERE email = :email AND is_active = 1 LIMIT 1";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            
            if ($stmt->rowCount() === 0) {
                // Don't reveal if email exists for security
                return [
                    'success' => true,
                    'message' => 'If the email exists, a reset token has been sent'
                ];
            }
            
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Generate reset token
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // Store token in database
            $query = "INSERT INTO {$this->reset_table} (user_id, token, expires_at) 
                      VALUES (:user_id, :token, :expires_at)";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':user_id', $user['id']);
            $stmt->bindParam(':token', $token);
            $stmt->bindParam(':expires_at', $expires);
            $stmt->execute();
            
            // In production, send email here
            // For testing, return token in console
            error_log("Password reset token for {$email}: {$token}");
            
            return [
                'success' => true,
                'message' => 'If the email exists, a reset token has been sent',
                'token' => $token // Remove this in production!
            ];
            
        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => 'Database error: ' . $e->getMessage()
            ];
        }
    }
    
    // Reset password
    public function resetPassword($token, $new_password) {
        try {
            // Validate token
            $query = "SELECT user_id FROM {$this->reset_table} 
                      WHERE token = :token AND expires_at > NOW() AND used = 0 
                      LIMIT 1";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':token', $token);
            $stmt->execute();
            
            if ($stmt->rowCount() === 0) {
                return [
                    'success' => false,
                    'message' => 'Invalid or expired reset token'
                ];
            }
            
            $reset = $stmt->fetch(PDO::FETCH_ASSOC);
            $user_id = $reset['user_id'];
            
            // Update password
            $hashed_password = password_hash($new_password, PASSWORD_BCRYPT, ['cost' => 12]);
            
            $query = "UPDATE {$this->table} SET password = :password WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':password', $hashed_password);
            $stmt->bindParam(':id', $user_id);
            $stmt->execute();
            
            // Mark token as used
            $query = "UPDATE {$this->reset_table} SET used = 1 WHERE token = :token";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':token', $token);
            $stmt->execute();
            
            return [
                'success' => true,
                'message' => 'Password reset successful'
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