<?php

namespace App\Controllers;

use App\Models\Cart;
use App\Providers\Validation\ValidateTokenProvider;
use PDO;

class CartController
{
    protected $cart;
    protected $tokenValidator;

    public function __construct(PDO $db, ValidateTokenProvider $tokenValidator)
    {
        $this->cart = new Cart($db);
        $this->tokenValidator = $tokenValidator;
    }

    /* ====== Add Product to Cart ====== */
    public function addProductToCart($token, $productId, $quantity)
    {
        // Validate the token
        $decoded = $this->tokenValidator->validateToken($token);
        if (!isset($decoded->uuid) || !isset($decoded->role) || $decoded->role !== '0001') {
            return json_encode(['status' => 'error', 'message' => 'Unauthorized access. Only buyers can add products to the cart.']);
        }

        $userId = $decoded->uuid;

        // Add the item to the cart
        $result = $this->cart->addItemToCart($userId, $productId, $quantity);
        return $result ? json_encode(['status' => 'success', 'message' => 'Product added to cart successfully']) 
                       : json_encode(['status' => 'error', 'message' => 'Failed to add product to cart']);
    }

    /* ====== View Cart ====== */
    public function viewCart($token)
    {
        // Validate the token
        $decoded = $this->tokenValidator->validateToken($token);
        if (!isset($decoded->uuid) || !isset($decoded->role) || $decoded->role !== '0001') {
            return json_encode(['status' => 'error', 'message' => 'Unauthorized access. Only buyers can view the cart.']);
        }

        $userId = $decoded->uuid;

        // Retrieve the cart items for this user
        $items = $this->cart->getCartItems($userId);

        return json_encode([
            'status' => 'success',
            'data' => $items,
            'total_items' => count($items),
            'total_price' => array_sum(array_column($items, 'total'))
        ]);
    }

    /* ====== Remove Product from Cart ====== */
    public function removeProductFromCart($token, $productId)
    {
        // Validate the token
        $decoded = $this->tokenValidator->validateToken($token);
        if (!isset($decoded->uuid) || !isset($decoded->role) || $decoded->role !== '0001') {
            return json_encode(['status' => 'error', 'message' => 'Unauthorized access. Only buyers can remove items from the cart.']);
        }

        $userId = $decoded->uuid;

        // Remove the item from the cart
        $result = $this->cart->removeItemFromCart($userId, $productId);
        return $result ? json_encode(['status' => 'success', 'message' => 'Product removed from cart successfully']) 
                       : json_encode(['status' => 'error', 'message' => 'Failed to remove product from cart']);
    }

    /* ====== Clear Cart ====== */
    public function clearCart($token)
    {
        // Validate the token
        $decoded = $this->tokenValidator->validateToken($token);
        if (!isset($decoded->uuid) || !isset($decoded->role) || $decoded->role !== '0001') {
            return json_encode(['status' => 'error', 'message' => 'Unauthorized access. Only buyers can clear the cart.']);
        }

        $userId = $decoded->uuid;

        // Clear all items from the user's cart
        $result = $this->cart->clearCart($userId);
        return $result ? json_encode(['status' => 'success', 'message' => 'Cart cleared successfully']) 
                       : json_encode(['status' => 'error', 'message' => 'Failed to clear cart']);
    }
}
