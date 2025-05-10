<?php

namespace Routes\BuyerRoute;

use App\Controllers\BuyerController;
use App\Providers\Validation\ValidateTokenProvider;
use Config\DatabaseConnection;

class BuyerRoute
{
    private $db;
    private $tokenValidator;

    public function __construct()
    {
        // Instantiate DatabaseConnection and ValidateTokenProvider
        $dbConnection = new DatabaseConnection();
        $this->db = $dbConnection->getConnection();
        $this->tokenValidator = new ValidateTokenProvider($this->db);
    }

    public function handleBuyerRoute($uri, $method)
    {
        // Instantiate BuyerController with dependencies
        $buyerController = new BuyerController($this->db, $this->tokenValidator);

        // Normalize URI for accurate matching
        $uri = str_replace('/buyer', '', $uri);

        // Retrieve and validate the JWT token
        $token = $this->getBearerToken();
        if (!$token) {
            $this->sendErrorResponse('Unauthorized: Token missing', 401);
            return;
        }

        if (!$this->isTokenValid($token)) {
            $this->sendErrorResponse('Unauthorized: Invalid token', 401);
            return;
        }

        // Route handling
        switch ($uri) {
            case '/cart/add':
                if ($method === 'POST') {
                    $data = json_decode(file_get_contents('php://input'), true);
                    echo $buyerController->addProductToCart($token, $data['product_id'], $data['quantity']);
                } else {
                    $this->sendErrorResponse('Method Not Allowed', 405);
                }
                break;

            case '/cart/view':
                if ($method === 'GET') {
                    echo $buyerController->viewCart($token);
                } else {
                    $this->sendErrorResponse('Method Not Allowed', 405);
                }
                break;

            case '/cart/update':
                if ($method === 'PUT') { // Use PUT for updates
                    $data = json_decode(file_get_contents('php://input'), true);

                    // Validate input
                    if (empty($data['product_id']) || !isset($data['quantity'])) {
                        $this->sendErrorResponse('Product ID and quantity are required', 400);
                        break;
                    }

                    // Call the updateProductInCart method in the BuyerController
                    echo $buyerController->updateProductInCart($token, $data['product_id'], $data['quantity']);
                } else {
                    $this->sendErrorResponse('Method Not Allowed', 405);
                }
                break;

            case '/cart/remove':
                if ($method === 'DELETE') {
                    $data = json_decode(file_get_contents('php://input'), true);
                    echo $buyerController->removeProductFromCart($token, $data['product_id']);
                } else {
                    $this->sendErrorResponse('Method Not Allowed', 405);
                }
                break;

            case '/cart/clear':
                if ($method === 'DELETE') { // Use DELETE for clearing the cart
                    echo $buyerController->clearCart($token);
                } else {
                    $this->sendErrorResponse('Method Not Allowed', 405);
                }
                break;

            case '/cart/checkout':
                if ($method === 'POST') {
                    $data = json_decode(file_get_contents('php://input'), true);

                    if (!is_array($data)) {
                        $this->sendErrorResponse('Invalid JSON payload', 400);
                        break;
                    }

                    error_log("Checkout Input Data: " . json_encode($data));

                    echo $buyerController->checkoutCart($token, $data);
                } else {
                    $this->sendErrorResponse('Method Not Allowed', 405);
                }
                break;


            case '/cart/checkout/initiate':
                if ($method === 'POST') {
                    $data = json_decode(file_get_contents('php://input'), true);

                    // Validate input
                    if (empty($data)) {
                        $this->sendErrorResponse('Invalid input data', 400);
                        break;
                    }

                    echo $buyerController->checkoutCart($token, $data);
                } else {
                    $this->sendErrorResponse('Method Not Allowed', 405);
                }
                break;

            case '/checkout/payment':
                if ($method === 'POST') {
                    $data = json_decode(file_get_contents('php://input'), true);

                    // Validate JSON payload
                    if (!is_array($data)) {
                        $this->sendErrorResponse('Invalid JSON payload', 400);
                        break;
                    }

                    // Pass to controller
                    echo $buyerController->checkoutPayment($token, $data);
                } else {
                    $this->sendErrorResponse('Method Not Allowed', 405);
                }
                break;


            case '/checkout/review':
                if ($method === 'GET') {
                    $checkoutId = $_GET['checkoutId'] ?? null;
                    echo $buyerController->reviewOrder($token, $checkoutId);
                } else {
                    $this->sendErrorResponse('Method Not Allowed', 405);
                }
                break;

            case '/checkout/confirm':
                if ($method === 'POST') {
                    $data = json_decode(file_get_contents('php://input'), true);

                    if (!is_array($data) || empty($data['order_id'])) {
                        $this->sendErrorResponse('order_id is required', 400);
                        break;
                    }

                    echo $buyerController->confirmOrder($token, $data['order_id']);
                } else {
                    $this->sendErrorResponse('Method Not Allowed', 405);
                }
                break;


            case '/checkout/cancel':
                if ($method === 'POST') {
                    $data = json_decode(file_get_contents('php://input'), true);
                    echo $buyerController->cancelCheckout($token, $data['order_id']);
                } else {
                    $this->sendErrorResponse('Method Not Allowed', 405);
                }
                break;

            default:
                $this->sendErrorResponse('Route not found', 404);
                break;
        }
    }

    // Token validation
    private function isTokenValid($token)
    {
        try {
            $this->tokenValidator->validateToken($token);
            return true;
        } catch (\Exception $e) {
            error_log('Token validation failed: ' . $e->getMessage());
            return false;
        }
    }

    // Extract Bearer token from the request headers
    private function getBearerToken()
    {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if (!empty($authHeader) && strpos($authHeader, 'Bearer ') === 0) {
            return str_replace('Bearer ', '', $authHeader);
        }
        return null; // Return null if token is not found
    }

    // Send JSON error response
    private function sendErrorResponse($message, $statusCode = 400)
    {
        echo json_encode([
            'status' => 'error',
            'message' => $message
        ]);
        http_response_code($statusCode);
    }
}
