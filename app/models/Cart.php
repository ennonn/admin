<?php

namespace App\Models;

use Config\DatabaseConnection;
use PDO;

class Cart
{
    private $db;

    public function __construct()
    {
        $this->db = DatabaseConnection::getConnection();
    }

    /* ====== Add Item to Cart ====== */
    public function addItemToCart($userId, $productId, $quantity)
    {
        $query = $this->db->prepare("
            INSERT INTO cart (user_id, product_id, quantity) 
            VALUES (:user_id, :product_id, :quantity)
            ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)
        ");
        $query->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $query->bindParam(':product_id', $productId, PDO::PARAM_INT);
        $query->bindParam(':quantity', $quantity, PDO::PARAM_INT);

        return $query->execute();
    }

    /* ====== View Cart Items ====== */
    public function getCartItems($userId)
    {
        $query = $this->db->prepare("
            SELECT 
                p.id AS product_id, 
                p.product_name, 
                p.price, 
                c.quantity, 
                (p.price * c.quantity) AS total 
            FROM cart c 
            JOIN products p ON c.product_id = p.id 
            WHERE c.user_id = :user_id
        ");
        $query->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $query->execute();
        $items = $query->fetchAll(PDO::FETCH_ASSOC);

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
            UPDATE cart 
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
            DELETE FROM cart 
            WHERE user_id = :user_id AND product_id = :product_id
        ");
        $query->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $query->bindParam(':product_id', $productId, PDO::PARAM_INT);

        return $query->execute();
    }

    /* ====== Clear Entire Cart ====== */
    public function clearCart($userId)
    {
        $query = $this->db->prepare("
            DELETE FROM cart 
            WHERE user_id = :user_id
        ");
        $query->bindParam(':user_id', $userId, PDO::PARAM_INT);

        return $query->execute();
    }
}
