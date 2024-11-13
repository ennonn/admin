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

    /* ====== Create Order ====== */
    public function createOrder($userId, $orderData)
    {
        // Begin transaction
        $this->db->beginTransaction();

        try {
            // Insert order into the `orders` table
            $orderQuery = $this->db->prepare("
                INSERT INTO orders (user_id, total_amount, status, created_at) 
                VALUES (:user_id, :total_amount, :status, NOW())
            ");
            $orderQuery->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $orderQuery->bindParam(':total_amount', $orderData['total_amount']);
            $orderQuery->bindParam(':status', $orderData['status']);
            $orderQuery->execute();

            $orderId = $this->db->lastInsertId();

            // Insert each item into the `order_items` table
            $itemQuery = $this->db->prepare("
                INSERT INTO order_items (order_id, product_id, quantity, price) 
                VALUES (:order_id, :product_id, :quantity, :price)
            ");
            foreach ($orderData['items'] as $item) {
                $itemQuery->bindParam(':order_id', $orderId, PDO::PARAM_INT);
                $itemQuery->bindParam(':product_id', $item['product_id'], PDO::PARAM_INT);
                $itemQuery->bindParam(':quantity', $item['quantity'], PDO::PARAM_INT);
                $itemQuery->bindParam(':price', $item['price']);
                $itemQuery->execute();
            }

            $this->db->commit();
            return [
                'status' => 'success',
                'order_id' => $orderId,
                'message' => 'Order created successfully'
            ];
        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log("Order creation failed: " . $e->getMessage());
            return [
                'status' => 'error',
                'message' => 'Failed to create order'
            ];
        }
    }

    /* ====== Get Order History ====== */
    public function getOrderHistory($userId)
    {
        $query = $this->db->prepare("
            SELECT orders.order_id, orders.total_amount, orders.status, orders.created_at,
                   order_items.product_id, order_items.quantity, order_items.price 
            FROM orders
            JOIN order_items ON orders.order_id = order_items.order_id
            WHERE orders.user_id = :user_id 
            ORDER BY orders.created_at DESC
        ");
        $query->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $query->execute();
        $orders = $query->fetchAll(PDO::FETCH_ASSOC);

        if (!$orders) {
            return [
                'status' => 'success',
                'message' => 'No orders found for this user',
                'data' => []
            ];
        }

        $orderHistory = [];
        foreach ($orders as $order) {
            $orderId = $order['order_id'];
            if (!isset($orderHistory[$orderId])) {
                $orderHistory[$orderId] = [
                    'order_id' => $orderId,
                    'total_amount' => $order['total_amount'],
                    'status' => $order['status'],
                    'created_at' => $order['created_at'],
                    'items' => []
                ];
            }
            $orderHistory[$orderId]['items'][] = [
                'product_id' => $order['product_id'],
                'quantity' => $order['quantity'],
                'price' => $order['price']
            ];
        }

        return [
            'status' => 'success',
            'data' => array_values($orderHistory)
        ];
    }
}
