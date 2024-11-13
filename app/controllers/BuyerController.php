<?php

namespace App\Controllers;

use App\Controllers\CartController;
use App\Controllers\CheckoutController;
use App\Providers\Validation\ValidateTokenProvider;

class BuyerController
{
    protected $cartController;
    protected $checkoutController;
    protected $tokenValidator;

    public function __construct()
    {
        $this->cartController = new CartController();
        $this->checkoutController = new CheckoutController();
        $this->tokenValidator = new ValidateTokenProvider();
    }

    // Check if user is a buyer based on the token
    private function isBuyer($token)
    {
        $decoded = $this->tokenValidator->validateToken($token);
        return isset($decoded->role) && $decoded->role === '0001';  // '0001' represents the buyer role
    }

    // Add a product to the buyer's cart
    public function addProductToCart($token, $productId, $quantity)
    {
        if (!$this->isBuyer($token)) {
            return json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        }
        $userId = $this->tokenValidator->getUserIdFromToken($token);
        return $this->cartController->addItemToCart($userId, $productId, $quantity);
    }

    // View the contents of the buyer's cart
    public function viewCart($token)
    {
        if (!$this->isBuyer($token)) {
            return json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        }
        $userId = $this->tokenValidator->getUserIdFromToken($token);
        return $this->cartController->viewCart($userId);
    }

    // Remove a product from the buyer's cart
    public function removeProductFromCart($token, $productId)
    {
        if (!$this->isBuyer($token)) {
            return json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        }
        $userId = $this->tokenValidator->getUserIdFromToken($token);
        return $this->cartController->removeItemFromCart($userId, $productId);
    }

    // Proceed to checkout for the items in the buyer's cart
    public function checkoutCart($token, $orderData)
    {
        if (!$this->isBuyer($token)) {
            return json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        }
        $userId = $this->tokenValidator->getUserIdFromToken($token);
        return $this->checkoutController->initiateCheckout($userId, $orderData);
    }
}
