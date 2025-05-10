<?php

namespace App\Models;

use PDO;

class Order
{
    private $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    // Create a new order
    public function createOrder($userId, $orderData)
    {
        $this->db->beginTransaction();

        try {
            // Insert into `order_table`
            $orderQuery = $this->db->prepare("
            INSERT INTO order_table (user_id, total_amount, status, payment_method, order_date) 
            VALUES (:user_id, :total_amount, :status, :payment_method, NOW())
        ");
            $orderQuery->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $orderQuery->bindParam(':total_amount', $orderData['total_amount']);
            $orderQuery->bindParam(':status', $orderData['status']);
            $orderQuery->bindParam(':payment_method', $orderData['payment_method']);

            if (!$orderQuery->execute()) {
                error_log("Order Table Insert Error: " . json_encode($orderQuery->errorInfo()));
                throw new \Exception("Failed to insert order into order_table");
            }

            $orderId = $this->db->lastInsertId();
            error_log("Order ID created successfully: $orderId");

            // Insert items into `order_item`
            $itemQuery = $this->db->prepare("
            INSERT INTO order_item (order_id, user_id, product_id, quantity, price) 
            VALUES (:order_id, :user_id, :product_id, :quantity, :price)
        ");
            foreach ($orderData['items'] as $item) {
                $itemQuery->bindParam(':order_id', $orderId, PDO::PARAM_INT);
                $itemQuery->bindParam(':user_id', $userId, PDO::PARAM_INT);
                $itemQuery->bindParam(':product_id', $item['product_id'], PDO::PARAM_INT);
                $itemQuery->bindParam(':quantity', $item['quantity'], PDO::PARAM_INT);
                $itemQuery->bindParam(':price', $item['price']);

                if (!$itemQuery->execute()) {
                    error_log("Order Item Insert Error: " . json_encode($itemQuery->errorInfo()));
                    throw new \Exception("Failed to insert item into order_item");
                }
            }

            // Commit transaction
            $this->db->commit();
            error_log("Order transaction committed successfully");

            return [
                'status' => 'success',
                'order_id' => $orderId,
                'message' => 'Order created successfully'
            ];
        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log("Order creation transaction failed: " . $e->getMessage());
            return [
                'status' => 'error',
                'message' => 'Failed to create order'
            ];
        }
    }

    public function getOrderDetails($checkoutId)
    {
        $query = $this->db->prepare("
        SELECT * FROM order_table WHERE order_id = :checkoutId
    ");
        $query->bindParam(':checkoutId', $checkoutId, PDO::PARAM_INT);
        $query->execute();

        $order = $query->fetch(PDO::FETCH_ASSOC);
        if (!$order) {
            return null; // Return null if no order found
        }

        // Fetch order items
        $itemQuery = $this->db->prepare("
        SELECT * FROM order_item WHERE order_id = :checkoutId
    ");
        $itemQuery->bindParam(':checkoutId', $checkoutId, PDO::PARAM_INT);
        $itemQuery->execute();

        $items = $itemQuery->fetchAll(PDO::FETCH_ASSOC);
        $order['items'] = $items;

        return $order;
    }

    public function confirmOrder($checkoutId)
    {
        $query = $this->db->prepare("
            UPDATE order_table SET status = 'confirmed' WHERE order_id = :checkoutId
        ");
        $query->bindParam(':checkoutId', $checkoutId, PDO::PARAM_INT);
        return $query->execute();
    }

    public function processPayment($userId, $paymentData)
    {
        try {
            // Prepare SQL query to insert payment data into the `payment` table
            $paymentQuery = $this->db->prepare("
            INSERT INTO payment (order_id, user_id, payment_date, payment_amount, payment_status)
            VALUES (:order_id, :user_id, NOW(), :payment_amount, :payment_status)
        ");

            // Bind parameters
            $paymentQuery->bindParam(':order_id', $paymentData['order_id'], PDO::PARAM_INT);
            $paymentQuery->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $paymentQuery->bindParam(':payment_amount', $paymentData['amount'], PDO::PARAM_STR);
            $paymentQuery->bindParam(':payment_status', $paymentData['status'], PDO::PARAM_STR);

            // Execute the query
            if ($paymentQuery->execute()) {
                error_log("Payment successfully processed for user $userId with order ID {$paymentData['order_id']}");
                return true;
            } else {
                // Log error details if query fails
                error_log("Failed to insert payment: " . json_encode($paymentQuery->errorInfo()));
                return false;
            }
        } catch (\Exception $e) {
            // Log any exception that occurs
            error_log("Error processing payment for user $userId: " . $e->getMessage());
            return false;
        }
    }


    public function cancelCheckout($checkoutId)
    {
        $query = $this->db->prepare("
            UPDATE order_table SET status = 'cancelled' WHERE order_id = :checkoutId
        ");
        $query->bindParam(':checkoutId', $checkoutId, PDO::PARAM_INT);
        return $query->execute();
    }
}
