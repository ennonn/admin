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

    public function initiateCheckout($userId, $orderData)
    {
        $checkoutSession = $this->order->createOrder($userId, $orderData);
        
        return $checkoutSession 
            ? json_encode(['status' => 'success', 'data' => $checkoutSession]) 
            : json_encode(['status' => 'error', 'message' => 'Failed to initiate checkout']);
    }

    public function reviewOrder($checkoutId)
    {
        $orderDetails = $this->order->getOrderDetails($checkoutId);

        return $orderDetails 
            ? json_encode(['status' => 'success', 'data' => $orderDetails]) 
            : json_encode(['status' => 'error', 'message' => 'Order review failed']);
    }

    public function confirmOrder($checkoutId)
    {
        $confirmationResult = $this->order->confirmOrder($checkoutId);

        return $confirmationResult 
            ? json_encode(['status' => 'success', 'message' => 'Order confirmed successfully']) 
            : json_encode(['status' => 'error', 'message' => 'Failed to confirm order']);
    }

    public function cancelCheckout($checkoutId)
    {
        $cancellationResult = $this->order->cancelCheckout($checkoutId);

        return $cancellationResult 
            ? json_encode(['status' => 'success', 'message' => 'Checkout cancelled successfully']) 
            : json_encode(['status' => 'error', 'message' => 'Failed to cancel checkout']);
    }
}
