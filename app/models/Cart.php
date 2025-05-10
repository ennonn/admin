<?php

namespace App\Models;

use PDO;

class Cart
{
    private $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /* ====== Add Item to Cart ====== */
    public function addItemToCart($userId, $productId, $quantity)
    {
        $query = $this->db->prepare("
            INSERT INTO cart_item (user_id, product_id, quantity) 
            VALUES (:user_id, :product_id, :quantity)
            ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)
        ");
        $query->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $query->bindParam(':product_id', $productId, PDO::PARAM_INT);
        $query->bindParam(':quantity', $quantity, PDO::PARAM_INT);

        return $query->execute();
    }

    /* ====== View Cart Items ====== */
    public function getCartItems($userId, $productId = null)
    {
        $query = "
        SELECT 
            p.product_id AS product_id, 
            p.product_name, 
            p.price, 
            ci.quantity, 
            (p.price * ci.quantity) AS total 
        FROM cart_item ci
        JOIN product p ON ci.product_id = p.product_id 
        WHERE ci.user_id = :user_id
    ";

        // Add product filter if productId is provided
        if ($productId !== null) {
            $query .= " AND ci.product_id = :product_id";
        }

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);

        if ($productId !== null) {
            $stmt->bindParam(':product_id', $productId, PDO::PARAM_INT);
        }

        $stmt->execute();

        if ($productId !== null) {
            // Return a single product if productId is provided
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }

        // Otherwise, return all items
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Calculate total items and total price
        $totalItems = array_sum(array_column($items, 'quantity'));
        $totalPrice = array_sum(array_column($items, 'total'));

        return [
            'cart_id' => uniqid('cart_'),
            'user_id' => $userId,
            'items' => $items,
            'total_items' => $totalItems,
            'total_price' => $totalPrice
        ];
    }

    /* ====== Update Item Quantity in Cart ====== */
    public function updateCartItem($userId, $productId, $quantity)
    {
        $query = $this->db->prepare("
            UPDATE cart_item 
            SET quantity = :quantity 
            WHERE user_id = :user_id AND product_id = :product_id
        ");
        $query->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $query->bindParam(':product_id', $productId, PDO::PARAM_INT);
        $query->bindParam(':quantity', $quantity, PDO::PARAM_INT);

        return $query->execute();
    }

    /* ====== Remove Item from Cart ====== */
    public function removeItemFromCart($userId, $productId)
    {
        $query = $this->db->prepare("
        DELETE FROM cart_item 
        WHERE user_id = :user_id AND product_id = :product_id
    ");
        $query->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $query->bindParam(':product_id', $productId, PDO::PARAM_INT);

        $query->execute();

        // Check if any rows were affected
        if ($query->rowCount() > 0) {
            return true; // Product was removed
        }

        return false; // Product was not in the cart
    }

    /* ====== Clear Entire Cart ====== */
    public function clearCart($userId)
    {
        $query = $this->db->prepare("
        DELETE FROM cart_item 
        WHERE user_id = :user_id
    ");
        $query->bindParam(':user_id', $userId, PDO::PARAM_INT);

        return $query->execute();
    }
}
