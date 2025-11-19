<?php
require_once __DIR__ . '/../config/database.php';

class Order {
    private $conn;
    private $orders_table = 'orders';
    private $order_items_table = 'order_items';

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    // Create order
    public function createOrder($user_id, $items, $total, $payment_method = 'card', $shipping_address = null) {
        try {
            // Start transaction
            $this->conn->beginTransaction();
            
            // Create order
            $order_number = 'ORD-' . strtoupper(uniqid());
            $query = "INSERT INTO {$this->orders_table} 
                        (user_id, order_number, total_amount, payment_method, shipping_address, status) 
                        VALUES (:user_id, :order_number, :total_amount, :payment_method, :shipping_address, 'pending')";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':order_number', $order_number);
            $stmt->bindParam(':total_amount', $total);
            $stmt->bindParam(':payment_method', $payment_method);
            $stmt->bindParam(':shipping_address', $shipping_address);
            $stmt->execute();
            
            $order_id = $this->conn->lastInsertId();
            
            // Add order items
            $query = "INSERT INTO {$this->order_items_table} 
                        (order_id, product_id, product_name, product_price, quantity) 
                        VALUES (:order_id, :product_id, :product_name, :product_price, :quantity)";
            
            $stmt = $this->conn->prepare($query);
            
            foreach ($items as $item) {
                $stmt->bindParam(':order_id', $order_id);
                $stmt->bindParam(':product_id', $item['id']);
                $stmt->bindParam(':product_name', $item['name']);
                $stmt->bindParam(':product_price', $item['price']);
                $stmt->bindParam(':quantity', $item['quantity']);
                $stmt->execute();
            }
            
            // Commit transaction
            $this->conn->commit();
            
            return [
                'success' => true,
                'message' => 'Order placed successfully',
                'order_id' => $order_id,
                'order_number' => $order_number
            ];
            
        } catch (PDOException $e) {
            $this->conn->rollBack();
            return [
                'success' => false,
                'message' => 'Database error: ' . $e->getMessage()
            ];
        }
    }

    // Get user orders
    public function getUserOrders($user_id) {
        $query = "SELECT o.*, 
                    GROUP_CONCAT(
                        CONCAT(oi.product_name, '|', oi.quantity, '|', oi.product_price) 
                        SEPARATOR ';'
                    ) as items
                    FROM {$this->orders_table} o
                    LEFT JOIN {$this->order_items_table} oi ON o.id = oi.order_id
                    WHERE o.user_id = :user_id
                    GROUP BY o.id
                    ORDER BY o.created_at DESC";
            
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        
        $orders = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            // Parse items
            $items = [];
            if ($row['items']) {
                $item_strings = explode(';', $row['items']);
                foreach ($item_strings as $item_string) {
                    list($name, $quantity, $price) = explode('|', $item_string);
                    $items[] = [
                        'name' => $name,
                        'quantity' => (int)$quantity,
                        'price' => (float)$price
                    ];
                }
            }
            
            $row['items'] = $items;
            $orders[] = $row;
        }
        
        return $orders;
    }

    // Get order by ID
    public function getOrderById($order_id, $user_id) {
        $query = "SELECT * FROM {$this->orders_table} 
                    WHERE id = :order_id AND user_id = :user_id LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':order_id', $order_id);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>