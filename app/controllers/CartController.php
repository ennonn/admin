<?php

namespace App\Controllers;

use App\Models\Cart;
use App\Providers\Validation\ValidateTokenProvider;
use PDO;

class CartController
{
    protected $db;
    protected $cart;
    protected $tokenValidator;

    public function __construct(PDO $db, ValidateTokenProvider $tokenValidator)
    {
        $this->db = $db;
        $this->cart = new Cart($db);
        $this->tokenValidator = $tokenValidator;
    }

    public function addProductToCart($userId, $productId, $quantity)
    {
        // Check if the product exists in the product table
        $query = $this->db->prepare("SELECT COUNT(*) FROM product WHERE product_id = :product_id");
        $query->bindParam(':product_id', $productId, PDO::PARAM_INT);
        $query->execute();
        $productExists = $query->fetchColumn();

        if (!$productExists) {
            return json_encode(['status' => 'error', 'message' => 'Product ID does not exist in the product table']);
        }

        // If product exists, add it to the cart
        $result = $this->cart->addItemToCart($userId, $productId, $quantity);
        return $result
            ? json_encode(['status' => 'success', 'message' => 'Product added to cart successfully'])
            : json_encode(['status' => 'error', 'message' => 'Failed to add product to cart']);
    }

    public function viewCart($userId)
    {
        $items = $this->cart->getCartItems($userId);
        return json_encode([
            'status' => 'success',
            'data' => $items
        ]);
    }

    public function removeProductFromCart($userId, $productId)
    {
        $result = $this->cart->removeItemFromCart($userId, $productId);
        return $result ? json_encode(['status' => 'success', 'message' => 'Product removed from cart successfully'])
            : json_encode(['status' => 'error', 'message' => 'Failed to remove product from cart']);
    }

    public function updateProductInCart($userId, $productId, $quantity)
    {
        // Ensure the product exists in the cart before updating
        $productExists = $this->cart->getCartItems($userId, $productId);

        if (!$productExists) {
            return json_encode([
                'status' => 'error',
                'message' => 'Product not found in cart'
            ]);
        }

        if ($quantity <= 0) {
            return json_encode([
                'status' => 'error',
                'message' => 'Quantity must be greater than zero'
            ]);
        }

        $result = $this->cart->updateCartItem($userId, $productId, $quantity);

        return $result
            ? json_encode(['status' => 'success', 'message' => 'Product quantity updated successfully'])
            : json_encode(['status' => 'error', 'message' => 'Failed to update product quantity']);
    }


    public function clearCart($userId)
    {
        // Check if the cart is already empty
        $cartItems = $this->cart->getCartItems($userId);

        if (empty($cartItems['items'])) {
            return json_encode([
                'status' => 'info',
                'message' => 'Your cart is already empty'
            ]);
        }

        // Clear the cart
        $result = $this->cart->clearCart($userId);

        return $result
            ? json_encode(['status' => 'success', 'message' => 'Cart cleared successfully. The cart is now empty.'])
            : json_encode(['status' => 'error', 'message' => 'Failed to clear cart']);
    }
    public function getCartItems($userId)
    {
        try {
            $query = $this->db->prepare("
            SELECT * FROM cart_item WHERE user_id = :user_id
        ");
            $query->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $query->execute();

            return $query->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            error_log("Error fetching cart items: " . $e->getMessage());
            return false;
        }
    }
}
