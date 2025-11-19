<?php
require_once __DIR__ . '/../config/database.php';

class Wishlist {
    private $conn;
    private $table = 'wishlist';

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    // Add to wishlist
    public function addToWishlist($user_id, $product_id) {
        try {
            // Check if already in wishlist
            $query = "SELECT id FROM {$this->table} 
                        WHERE user_id = :user_id AND product_id = :product_id LIMIT 1";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':product_id', $product_id);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                return [
                    'success' => false,
                    'message' => 'Item already in wishlist'
                ];
            }
            
            // Add to wishlist
            $query = "INSERT INTO {$this->table} (user_id, product_id) 
                        VALUES (:user_id, :product_id)";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':product_id', $product_id);
            
            if ($stmt->execute()) {
                return [
                    'success' => true,
                    'message' => 'Added to wishlist'
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Failed to add to wishlist'
            ];
            
        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => 'Database error: ' . $e->getMessage()
            ];
        }
    }

    // Remove from wishlist
    public function removeFromWishlist($user_id, $product_id) {
        try {
            $query = "DELETE FROM {$this->table} 
                        WHERE user_id = :user_id AND product_id = :product_id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':product_id', $product_id);
            
            if ($stmt->execute()) {
                return [
                    'success' => true,
                    'message' => 'Removed from wishlist'
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Failed to remove from wishlist'
            ];
            
        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => 'Database error: ' . $e->getMessage()
            ];
        }
    }

    // Get wishlist
    public function getWishlist($user_id) {
        $query = "SELECT product_id, created_at FROM {$this->table} 
                    WHERE user_id = :user_id ORDER BY created_at DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Check if in wishlist
    public function isInWishlist($user_id, $product_id) {
        $query = "SELECT id FROM {$this->table} 
                    WHERE user_id = :user_id AND product_id = :product_id LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':product_id', $product_id);
        $stmt->execute();
        
        return $stmt->rowCount() > 0;
    }
}
?>