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

    public function addProductToCart($userId, $productId, $quantity)
    {
        $result = $this->cart->addItemToCart($userId, $productId, $quantity);
        return $result ? json_encode(['status' => 'success', 'message' => 'Product added to cart successfully']) 
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

    public function clearCart($userId)
    {
        $result = $this->cart->clearCart($userId);
        return $result ? json_encode(['status' => 'success', 'message' => 'Cart cleared successfully']) 
                       : json_encode(['status' => 'error', 'message' => 'Failed to clear cart']);
    }
}
