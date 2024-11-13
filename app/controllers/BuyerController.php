<?php

namespace App\Controllers;

use App\Controllers\CartController;
use App\Controllers\CheckoutController;
use App\Providers\Validation\ValidateTokenProvider;
use PDO;

class BuyerController
{
    protected $cartController;
    protected $checkoutController;
    protected $tokenValidator;

    public function __construct(PDO $db, ValidateTokenProvider $tokenValidator)
    {
        $this->cartController = new CartController($db, $tokenValidator);
        $this->checkoutController = new CheckoutController($db, $tokenValidator);
        $this->tokenValidator = $tokenValidator;
    }

    private function isBuyer($token)
    {
        $decoded = $this->tokenValidator->validateToken($token);
        return isset($decoded->role) && $decoded->role === '0001';  // '0001' represents the buyer role
    }

    public function addProductToCart($token, $productId, $quantity)
    {
        if (!$this->isBuyer($token)) {
            return json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        }
        $userId = $this->tokenValidator->validateToken($token)->uuid;
        return $this->cartController->addProductToCart($userId, $productId, $quantity);
    }

    public function viewCart($token)
    {
        if (!$this->isBuyer($token)) {
            return json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        }
        $userId = $this->tokenValidator->validateToken($token)->uuid;
        return $this->cartController->viewCart($userId);
    }

    public function removeProductFromCart($token, $productId)
    {
        if (!$this->isBuyer($token)) {
            return json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        }
        $userId = $this->tokenValidator->validateToken($token)->uuid;
        return $this->cartController->removeProductFromCart($userId, $productId);
    }

    public function checkoutCart($token, $orderData)
    {
        if (!$this->isBuyer($token)) {
            return json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        }
        $userId = $this->tokenValidator->validateToken($token)->uuid;
        return $this->checkoutController->initiateCheckout($userId, $orderData);
    }
}
