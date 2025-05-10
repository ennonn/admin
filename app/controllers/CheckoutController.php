<?php

namespace App\Controllers;

use App\Models\Order;
use App\Providers\Validation\ValidateTokenProvider;
use PDO;

class CheckoutController
{
    protected $db;
    protected $order;
    protected $tokenValidator;

    public function __construct(PDO $db, ValidateTokenProvider $tokenValidator)
    {
        $this->db = $db;
        $this->order = new Order($db);
        $this->tokenValidator = $tokenValidator;
    }

    public function initiateCheckout($userId, $paymentMethod)
    {
        try {
            // Validate payment method
            if (empty($paymentMethod)) {
                return json_encode(['status' => 'error', 'message' => 'Payment method is required, put your payment method']);
            }

            // Fetch cart items for the user
            $query = $this->db->prepare("
            SELECT ci.product_id, ci.quantity, p.price 
            FROM cart_item ci
            INNER JOIN product p ON ci.product_id = p.product_id
            WHERE ci.user_id = :user_id
        ");
            $query->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $query->execute();

            $cartItems = $query->fetchAll(PDO::FETCH_ASSOC);

            if (empty($cartItems)) {
                return json_encode(['status' => 'error', 'message' => 'Cart is empty']);
            }

            // Calculate total amount
            $totalAmount = 0;
            foreach ($cartItems as $item) {
                $totalAmount += $item['quantity'] * $item['price'];
            }

            // Start a transaction
            $this->db->beginTransaction();

            // Insert order into order_table
            $orderQuery = $this->db->prepare("
            INSERT INTO order_table (user_id, total_amount, status, payment_method)
            VALUES (:user_id, :total_amount, 'Pending', :payment_method)
        ");
            $orderQuery->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $orderQuery->bindParam(':total_amount', $totalAmount, PDO::PARAM_STR);
            $orderQuery->bindParam(':payment_method', $paymentMethod, PDO::PARAM_STR);
            $orderQuery->execute();

            $orderId = $this->db->lastInsertId(); // Get the newly created order ID

            // Insert items into order_item
            foreach ($cartItems as $item) {
                $itemQuery = $this->db->prepare("
                INSERT INTO order_item (order_id, user_id, product_id, quantity, price)
                VALUES (:order_id, :user_id, :product_id, :quantity, :price)
            ");
                $itemQuery->bindParam(':order_id', $orderId, PDO::PARAM_INT);
                $itemQuery->bindParam(':user_id', $userId, PDO::PARAM_INT);
                $itemQuery->bindParam(':product_id', $item['product_id'], PDO::PARAM_INT);
                $itemQuery->bindParam(':quantity', $item['quantity'], PDO::PARAM_INT);
                $itemQuery->bindParam(':price', $item['price'], PDO::PARAM_STR);
                $itemQuery->execute();
            }

            // Clear the cart after checkout
            $clearCartQuery = $this->db->prepare("DELETE FROM cart_item WHERE user_id = :user_id");
            $clearCartQuery->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $clearCartQuery->execute();

            // Commit the transaction
            $this->db->commit();

            // Return success with order details
            return json_encode([
                'status' => 'success',
                'message' => 'Checkout initiated successfully',
                'order_id' => $orderId,
                'total_amount' => $totalAmount,
                'payment_method' => $paymentMethod,
                'order_items' => $cartItems
            ]);
        } catch (\Exception $e) {
            $this->db->rollBack(); // Rollback transaction on failure
            error_log("Error in initiateCheckout: " . $e->getMessage());
            return json_encode(['status' => 'error', 'message' => 'Failed to initiate checkout']);
        }
    }


    public function reviewOrder($userId)
    {
        try {
            // Fetch the most recent order for the user
            $orderQuery = $this->db->prepare("
            SELECT order_id, total_amount, payment_method, status, order_date 
            FROM order_table
            WHERE user_id = :user_id
            ORDER BY order_date DESC
            LIMIT 1
        ");
            $orderQuery->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $orderQuery->execute();

            $order = $orderQuery->fetch(PDO::FETCH_ASSOC);

            // If no orders are found, return an error response
            if (!$order) {
                return json_encode([
                    'status' => 'error',
                    'message' => 'No checkout data available. Please complete a checkout to review.'
                ]);
            }

            // Fetch order items for the most recent order
            $itemsQuery = $this->db->prepare("
            SELECT product_id, quantity, price 
            FROM order_item
            WHERE order_id = :order_id
        ");
            $itemsQuery->bindParam(':order_id', $order['order_id'], PDO::PARAM_INT);
            $itemsQuery->execute();

            $orderItems = $itemsQuery->fetchAll(PDO::FETCH_ASSOC);

            // Combine order details with items
            $order['order_items'] = $orderItems;

            // Return the full order details
            return json_encode([
                'status' => 'success',
                'message' => 'Checkout review successfully.',
                'order' => $order
            ]);
        } catch (\Exception $e) {
            error_log("Error in reviewOrder: " . $e->getMessage());
            return json_encode([
                'status' => 'error',
                'message' => 'Failed to checkout review. Please try again later.'
            ]);
        }
    }

    public function confirmOrder($order_id)
    {
        if (empty($order_id)) {
            return json_encode(['status' => 'error', 'message' => 'Order ID is required.']);
        }

        try {
            // Check if the order exists and get its status
            $query = $this->db->prepare("SELECT status FROM order_table WHERE order_id = :order_id");
            $query->bindParam(':order_id', $order_id, PDO::PARAM_INT);
            $query->execute();
            $order = $query->fetch(PDO::FETCH_ASSOC);

            if (!$order) {
                return json_encode(['status' => 'error', 'message' => 'Order not found.']);
            }

            if ($order['status'] === 'Confirmed') {
                return json_encode(['status' => 'error', 'message' => 'Order is already confirmed.']);
            }

            // Confirm the order
            if ($this->order->confirmOrder($order_id)) {
                return json_encode(['status' => 'success', 'message' => 'Order confirmed successfully.']);
            }

            return json_encode(['status' => 'error', 'message' => 'Failed to confirm order.']);
        } catch (\Exception $e) {
            error_log("Error in confirmOrder: " . $e->getMessage());
            return json_encode(['status' => 'error', 'message' => 'An unexpected error occurred.']);
        }
    }


    public function processPayment($userId, $paymentData)
    {
        try {
            // Validate if the order_id exists in the order_table
            $orderCheckQuery = $this->db->prepare("
                SELECT COUNT(*) FROM order_table 
                WHERE order_id = :order_id AND user_id = :user_id
            ");
            $orderCheckQuery->bindParam(':order_id', $paymentData['order_id'], PDO::PARAM_INT);
            $orderCheckQuery->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $orderCheckQuery->execute();

            $orderExists = $orderCheckQuery->fetchColumn();

            if (!$orderExists) {
                return json_encode([
                    'status' => 'error',
                    'message' => 'Order not found. Please check the order id and try again.'
                ]);
            }

            // Validate payment amount and status
            if (empty($paymentData['amount']) || empty($paymentData['status'])) {
                return json_encode([
                    'status' => 'error',
                    'message' => 'Missing required payment details (amount or status).'
                ]);
            }

            // Insert the payment into the payment_table
            $query = $this->db->prepare("
                INSERT INTO payment_table (order_id, user_id, payment_date, payment_amount, payment_status)
                VALUES (:order_id, :user_id, NOW(), :payment_amount, :payment_status)
            ");
            $query->bindParam(':order_id', $paymentData['order_id'], PDO::PARAM_INT);
            $query->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $query->bindParam(':payment_amount', $paymentData['amount'], PDO::PARAM_STR);
            $query->bindParam(':payment_status', $paymentData['status'], PDO::PARAM_STR);

            if ($query->execute()) {
                return json_encode([
                    'status' => 'success',
                    'message' => 'Payment processed successfully.'
                ]);
            } else {
                return json_encode([
                    'status' => 'error',
                    'message' => 'Failed to process payment. Please try again later.'
                ]);
            }
        } catch (\Exception $e) {
            error_log("Error in processPayment: " . $e->getMessage());
            return json_encode([
                'status' => 'error',
                'message' => 'An unexpected error occurred while processing payment.'
            ]);
        }
    }

    public function cancelCheckout($order_id)
    {
        try {
            // Validate the input checkoutId
            if (empty($order_id)) {
                return json_encode(['status' => 'error', 'message' => 'order ID is required.']);
            }

            // Check if the checkout ID exists in the database
            $query = $this->db->prepare("
            SELECT COUNT(*) FROM order_table WHERE order_id = :order_id
        ");
            $query->bindParam(':order_id', $order_id, PDO::PARAM_INT);
            $query->execute();
            $checkoutExists = $query->fetchColumn();

            if (!$checkoutExists) {
                return json_encode(['status' => 'error', 'message' => 'order ID not found.']);
            }

            // Attempt to cancel the checkout using the Order model
            $cancellationResult = $this->order->cancelCheckout($order_id);

            return $cancellationResult
                ? json_encode(['status' => 'success', 'message' => 'Checkout cancelled successfully.'])
                : json_encode(['status' => 'error', 'message' => 'Failed to cancel checkout.']);
        } catch (\Exception $e) {
            error_log("Error in cancelCheckout: " . $e->getMessage());
            return json_encode(['status' => 'error', 'message' => 'An unexpected error occurred while cancelling the checkout.']);
        }
    }
}
