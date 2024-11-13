<?php

namespace App\Controllers;

use App\Models\Order;
use App\Providers\Validation\ValidateTokenProvider;
use PDO;

class CheckoutController
{
    protected $order;
    protected $tokenValidator;

    public function __construct(PDO $db, ValidateTokenProvider $tokenValidator)
    {
        $this->order = new Order($db);
        $this->tokenValidator = $tokenValidator;
    }

    /* ====== SECTION 1: Initiate Checkout ====== */

    public function initiateCheckout($token, $cartId, $shippingAddress)
    {
        $decoded = $this->validateBuyerToken($token);
        if (!$decoded) {
            return json_encode(['status' => 'error', 'message' => 'Unauthorized. Only buyers can initiate checkout.']);
        }

        // Initiate the checkout process and lock the cart
        $checkoutSession = $this->order->initiateCheckout($decoded->uuid, $cartId, $shippingAddress);
        
        return $checkoutSession 
            ? json_encode(['status' => 'success', 'data' => $checkoutSession]) 
            : json_encode(['status' => 'error', 'message' => 'Failed to initiate checkout']);
    }

    /* ====== SECTION 2: Choose Payment Method ====== */

    public function choosePaymentMethod($token, $checkoutId, $paymentMethod)
    {
        $decoded = $this->validateBuyerToken($token);
        if (!$decoded) {
            return json_encode(['status' => 'error', 'message' => 'Unauthorized. Only buyers can choose payment methods.']);
        }

        $paymentResult = $this->order->selectPaymentMethod($checkoutId, $paymentMethod);

        return $paymentResult 
            ? json_encode(['status' => 'success', 'data' => $paymentResult]) 
            : json_encode(['status' => 'error', 'message' => 'Failed to choose payment method']);
    }

    /* ====== SECTION 3: Review Order ====== */

    public function reviewOrder($token, $checkoutId)
    {
        $decoded = $this->validateBuyerToken($token);
        if (!$decoded) {
            return json_encode(['status' => 'error', 'message' => 'Unauthorized. Only buyers can review orders.']);
        }

        $orderDetails = $this->order->getOrderDetails($checkoutId);

        return $orderDetails 
            ? json_encode(['status' => 'success', 'data' => $orderDetails]) 
            : json_encode(['status' => 'error', 'message' => 'Order review failed']);
    }

    /* ====== SECTION 4: Confirm Order ====== */

    public function confirmOrder($token, $checkoutId)
    {
        $decoded = $this->validateBuyerToken($token);
        if (!$decoded) {
            return json_encode(['status' => 'error', 'message' => 'Unauthorized. Only buyers can confirm orders.']);
        }

        $confirmationResult = $this->order->confirmOrder($checkoutId);

        return $confirmationResult 
            ? json_encode(['status' => 'success', 'message' => 'Order confirmed successfully']) 
            : json_encode(['status' => 'error', 'message' => 'Failed to confirm order']);
    }

    /* ====== SECTION 5: Cancel Checkout ====== */

    public function cancelCheckout($token, $checkoutId)
    {
        $decoded = $this->validateBuyerToken($token);
        if (!$decoded) {
            return json_encode(['status' => 'error', 'message' => 'Unauthorized. Only buyers can cancel checkout.']);
        }

        $cancellationResult = $this->order->cancelCheckout($checkoutId);

        return $cancellationResult 
            ? json_encode(['status' => 'success', 'message' => 'Checkout cancelled successfully']) 
            : json_encode(['status' => 'error', 'message' => 'Failed to cancel checkout']);
    }

    /* ====== Helper Method: Validate Buyer Token ====== */

    private function validateBuyerToken($token)
    {
        $decoded = $this->tokenValidator->validateToken($token);
        if (!isset($decoded->uuid) || !isset($decoded->role) || $decoded->role !== '0001') {
            return null;
        }
        return $decoded;
    }
}
