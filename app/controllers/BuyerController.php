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

    public function clearCart($token)
    {
        if (!$this->isBuyer($token)) {
            return json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        }

        $userId = $this->tokenValidator->validateToken($token)->uuid;
        return $this->cartController->clearCart($userId);
    }

    public function updateProductInCart($token, $productId, $quantity)
    {
        if (!$this->isBuyer($token)) {
            return json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        }

        $userId = $this->tokenValidator->validateToken($token)->uuid;
        return $this->cartController->updateProductInCart($userId, $productId, $quantity);
    }

    public function checkoutCart($token, $requestBody)
    {
        if (!$this->isBuyer($token)) {
            return json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        }

        $userId = $this->tokenValidator->validateToken($token)->uuid;

        // Validate payment method in the request body
        if (empty($requestBody['payment_method'])) {
            return json_encode(['status' => 'error', 'message' => 'Payment method is required in the request body']);
        }

        $paymentMethod = $requestBody['payment_method'];

        // Initiate checkout using user's cart and provided payment method
        $checkoutResult = $this->checkoutController->initiateCheckout($userId, $paymentMethod);

        return $checkoutResult;
    }

    public function reviewOrder($token)
    {
        if (!$this->isBuyer($token)) {
            return json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        }

        $userId = $this->tokenValidator->validateToken($token)->uuid;

        // Automatically fetch and review the latest order
        $reviewResult = $this->checkoutController->reviewOrder($userId);

        return $reviewResult;
    }

    public function confirmOrder($token, $checkoutId)
    {
        if (!$this->isBuyer($token)) {
            return json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        }

        if (!$checkoutId) {
            return json_encode(['status' => 'error', 'message' => 'Checkout ID is required']);
        }

        return $this->checkoutController->confirmOrder($checkoutId);
    }

    public function checkoutPayment($token, $paymentData)
    {
        if (!$this->isBuyer($token)) {
            return json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        }

        $userId = $this->tokenValidator->validateToken($token)->uuid;

        // Validate payment data
        if (empty($paymentData['order_id']) || empty($paymentData['amount']) || empty($paymentData['status'])) {
            return json_encode(['status' => 'error', 'message' => 'Missing required payment details']);
        }

        // Process payment
        return $this->checkoutController->processPayment($userId, $paymentData);
    }

    public function cancelCheckout($token, $checkoutId)
    {
        if (!$this->isBuyer($token)) {
            return json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        }

        if (!$checkoutId) {
            return json_encode(['status' => 'error', 'message' => 'Checkout ID is required']);
        }

        return $this->checkoutController->cancelCheckout($checkoutId);
    }
}
